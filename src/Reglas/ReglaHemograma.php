<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;
use Validador\Texto;

/**
 * Regla de hemograma IPRESS-aware (v2).
 *
 * Familia de códigos hemograma (configurable):
 *   85004, 85025, 85027, 85007, 85013, 85014, 85018, 85032, 85049, 85590
 *
 * Representación válida por IPRESS (configurable vía config.php).  La IPRESS se
 * reconoce por su código RENIPRESS y, si el archivo no lo trae, por patrón
 * contenido en el nombre:
 *   Arequipa (11794) / Chiclayo (11833) → par {85027, 85007}, cantidades iguales
 *   A. B. Leguía (16094) / Geriátrico San José (14718) → {85025}
 *
 * Lógica por prestación:
 *   1. Si algún código del conjunto válido V está presente → conservarlo
 *      (y su par si V es un par y ambos están presentes).
 *      Eliminar todo otro código de la familia.
 *      Si V exige un código que no llegó → SUGERENCIA de agregarlo.
 *      Si los códigos de V llegaron en cantidades distintas → REVISAR.
 *   2. Si ninguno de V está presente pero hay CBC (85025 ó 85027):
 *      Conservar el CBC presente, emitir SUGERENCIA hacia el set V.
 *      Eliminar todo otro código de la familia.
 *   3. Sin V ni CBC → conservar el código de mayor valor, eliminar el resto.
 *      (Fallback para IPRESS no mapeada o family de componentes sueltos.)
 *
 * SUGERENCIA tiene prioridad propia (menor que ELIMINAR hemograma).
 *
 * Prioridad ELIMINAR: 3 / color ámbar.
 * Prioridad SUGERENCIA: 1 / color azul.
 */
class ReglaHemograma implements ReglaInterface
{
    /** Lookup O(1) de todos los códigos de la familia */
    private array $familia;

    /** [codigo_renipress_sin_ceros => list<string>] */
    private array $ipressPorCodigo;

    /** [patron_nombre_normalizado => list<string>] */
    private array $ipressPorNombre;

    /** Códigos CBC de respaldo cuando ningún código de V está presente */
    private const CBC_FALLBACK = ['85025', '85027'];

    public function __construct(
        private readonly string $colorElim,
        private readonly int    $prioridadElim,
        private readonly string $colorSug,
        private readonly int    $prioridadSug,
        array                   $codigos,
        array                   $ipressPorCodigo,
        array                   $ipressPorNombre,
    ) {
        $this->familia         = array_fill_keys($codigos, true);
        $this->ipressPorNombre = $ipressPorNombre;

        // Excel pierde los ceros a la izquierda al guardar el código como número
        $this->ipressPorCodigo = [];
        foreach ($ipressPorCodigo as $cod => $set) {
            $this->ipressPorCodigo[ltrim((string) $cod, '0')] = $set;
        }
    }

    public function codigo(): string { return 'HEMOGRAMA'; }
    public function nombre(): string { return 'Hemograma'; }
    public function color(): string  { return $this->colorElim; }
    public function prioridad(): int { return $this->prioridadElim; }

    public function evaluar(string $pk, array $atencion): array
    {
        // 1. Recolectar filas de la familia y nombre de IPRESS
        $presentes = []; // codigo => list<fila_array>
        $ipressNom = '';
        $ipressCod = '';

        foreach ($atencion as $f) {
            if ($ipressNom === '' && $f['ipress_nom'] !== '') {
                $ipressNom = $f['ipress_nom'];
            }
            if ($ipressCod === '' && $f['ipress_cod'] !== '') {
                $ipressCod = $f['ipress_cod'];
            }
            if (isset($this->familia[$f['codigo']])) {
                $presentes[$f['codigo']][] = $f;
            }
        }

        if (empty($presentes)) {
            return [];
        }

        // 2. Determinar el conjunto válido para esta IPRESS
        $setValido = $this->setValidoPara($ipressCod, $ipressNom);

        // 3. Aplicar lógica
        if ($setValido === null) {
            // IPRESS no mapeada: conservar el de mayor valor, eliminar el resto
            return $this->eliminarMenosMaxValor($pk, $presentes);
        }

        $setLookup      = array_fill_keys($setValido, true);
        $codigosValidos = array_values(array_filter(
            array_keys($presentes),
            static fn($c): bool => isset($setLookup[$c])
        ));

        if (!empty($codigosValidos)) {
            // Caso A: hay al menos un código del set válido presente → conservar todos los del set
            $conservar = array_fill_keys($codigosValidos, true);
            $obs       = $this->eliminarResto($pk, $presentes, $conservar, $codigosValidos);

            // El set puede exigir varios códigos juntos (Arequipa/Chiclayo: 85027 + 85007)
            $faltantes = array_values(array_filter(
                $setValido,
                static fn(string $c): bool => !isset($presentes[$c])
            ));

            if (!empty($faltantes)) {
                return array_merge($obs, $this->sugerirFaltantes($pk, $presentes, $codigosValidos, $faltantes));
            }

            return array_merge($obs, $this->avisarCantidades($pk, $presentes, $codigosValidos));
        }

        // Caso B: ningún código del set válido; buscar CBC de respaldo
        $cbcCodigo = null;
        foreach (self::CBC_FALLBACK as $cbc) {
            if (isset($presentes[$cbc])) {
                $cbcCodigo = $cbc;
                break;
            }
        }

        if ($cbcCodigo !== null) {
            $obs       = $this->emitirSugerencia($pk, $presentes[$cbcCodigo], $setValido, $cbcCodigo);
            $conservar = [$cbcCodigo => true];
            return array_merge($obs, $this->eliminarResto($pk, $presentes, $conservar, [$cbcCodigo]));
        }

        // Caso C: sin código del set ni CBC → fallback por valor
        return $this->eliminarMenosMaxValor($pk, $presentes);
    }

    // ── Helpers privados ─────────────────────────────────────────────────

    /**
     * Resuelve el conjunto válido en cascada: primero por CÓDIGO IPRESS, que es
     * exacto; si el archivo no lo trae, por patrón contenido en el nombre
     * normalizado ("hospital regional pnp arequipa" contiene "arequipa").
     * Devuelve null si ninguna IPRESS mapeada coincide.
     */
    private function setValidoPara(string $ipressCod, string $ipressNom): ?array
    {
        $cod = ltrim(trim($ipressCod), '0');
        if ($cod !== '' && isset($this->ipressPorCodigo[$cod])) {
            return $this->ipressPorCodigo[$cod];
        }

        $clave = Texto::clave($ipressNom);
        if ($clave === '') {
            return null;
        }

        foreach ($this->ipressPorNombre as $patron => $set) {
            if (str_contains($clave, (string) $patron)) {
                return $set;
            }
        }

        return null;
    }

    /**
     * El set válido exige varios códigos juntos pero solo llegó parte de ellos.
     * Se avisa una sola vez, sobre la primera fila del código sí presente.
     *
     * @param string[] $faltantes Códigos del set que no aparecen en la atención
     */
    private function sugerirFaltantes(
        string $pk,
        array  $presentes,
        array  $codigosValidos,
        array  $faltantes,
    ): array {
        $presente = (string) $codigosValidos[0];
        $filas    = $presentes[$presente];
        $f        = $filas[0];

        // Si el código viene repetido, la cantidad a igualar es la que quedará
        // tras consolidar: la suma, contada igual que ReglaCodigosDuplicados
        // (fila sin cantidad = 1).  Sin columna cantidad no hay número que dar.
        $conCantidad = array_filter($filas, static fn(array $x): bool => ($x['cantidad'] ?? null) !== null);
        $cantidad    = $conCantidad === [] ? 0 : array_sum(array_map(
            static fn(array $x): int => ($x['cantidad'] ?? 0) > 0 ? (int) $x['cantidad'] : 1,
            $filas
        ));

        $mismaCantidad = $cantidad > 0
            ? "en la misma cantidad que {$presente} ({$cantidad})"
            : "en la misma cantidad que {$presente}";

        return [new Observacion(
            fila:        $f['fila'],
            pk:          $pk,
            codigo:      $presente,
            valor:       $f['valor'],
            reglaCodigo: 'SUGERENCIA',
            reglaNombre: 'Sugerencia hemograma',
            prioridad:   $this->prioridadSug,
            color:       $this->colorSug,
            motivo:      "El registro válido de hemograma en esta IPRESS exige también "
                       . implode(' + ', $faltantes) . "; considerar agregarlo {$mismaCantidad}",
            accion:      'SUGERENCIA',
        )];
    }

    /**
     * Los códigos del set válido deben facturarse en cantidades iguales.
     * Si no coinciden se marcan todas sus filas para que el auditor decida;
     * la regla no elige cuál cantidad es la correcta.
     */
    private function avisarCantidades(string $pk, array $presentes, array $codigosValidos): array
    {
        if (count($codigosValidos) < 2) {
            return [];
        }

        $cantidades = [];
        foreach ($codigosValidos as $cod) {
            $total = 0;
            foreach ($presentes[$cod] as $f) {
                $total += (int) ($f['cantidad'] ?? 0);
            }
            $cantidades[(string) $cod] = $total;
        }

        // Sin columna cantidad todos quedan en 0 y no hay nada que comparar
        if (count(array_unique($cantidades)) < 2) {
            return [];
        }

        $detalle = [];
        foreach ($cantidades as $cod => $n) {
            $detalle[] = "{$cod} = {$n}";
        }
        $motivo = 'Cantidades distintas entre los códigos del hemograma ('
                . implode(', ', $detalle)
                . '); deben registrarse en la misma cantidad';

        // Solo en la primera fila de cada código: es la que sobrevive a la
        // consolidación de duplicados y donde el auditor ajusta la cantidad.
        $obs = [];
        foreach ($codigosValidos as $cod) {
            $f      = $presentes[$cod][0];
            $otros  = array_values(array_filter(
                $codigosValidos,
                static fn($c): bool => $c !== $cod
            ));

            $obs[] = new Observacion(
                fila:        $f['fila'],
                pk:          $pk,
                codigo:      (string) $f['codigo'],
                valor:       $f['valor'],
                reglaCodigo: $this->codigo(),
                reglaNombre: $this->nombre(),
                prioridad:   $this->prioridadElim,
                color:       $this->colorElim,
                motivo:      $motivo,
                accion:      'IGUALAR ' . implode(' + ', $otros),
            );
        }

        return $obs;
    }

    /**
     * Emite ELIMINAR para todos los códigos de $presentes que no estén en $conservarLookup.
     *
     * @param string[]                       $codigosConservados Para el mensaje
     */
    private function eliminarResto(
        string $pk,
        array  $presentes,
        array  $conservarLookup,
        array  $codigosConservados,
    ): array {
        $obs = [];
        $label = implode(' + ', $codigosConservados);
        foreach ($presentes as $cod => $filas) {
            if (isset($conservarLookup[$cod])) {
                continue;
            }
            foreach ($filas as $f) {
                $obs[] = new Observacion(
                    fila:        $f['fila'],
                    pk:          $pk,
                    codigo:      $f['codigo'],
                    valor:       $f['valor'],
                    reglaCodigo: $this->codigo(),
                    reglaNombre: $this->nombre(),
                    prioridad:   $this->prioridadElim,
                    color:       $this->colorElim,
                    motivo:      "Hemograma redundante; conservar " . (count($codigosConservados) > 1 ? "los códigos {$label}" : "el código {$label}"),
                    accion:      'ELIMINAR',
                );
            }
        }
        return $obs;
    }

    /**
     * Fallback para IPRESS no mapeada o sin ningún CBC:
     * conserva el código con mayor valor máximo; si solo hay 1 código
     * distinto no hay conflicto de redundancia → devuelve vacío.
     */
    private function eliminarMenosMaxValor(string $pk, array $presentes): array
    {
        if (count($presentes) < 2) {
            return [];
        }

        $maxPorCodigo = [];
        foreach ($presentes as $cod => $filas) {
            $maxPorCodigo[$cod] = max(array_map(fn($f) => (float) ($f['valor'] ?? 0.0), $filas));
        }
        arsort($maxPorCodigo);
        $conservar = (string) array_key_first($maxPorCodigo);

        return $this->eliminarResto($pk, $presentes, [$conservar => true], [$conservar]);
    }

    /**
     * Emite una SUGERENCIA solo en la primera fila del código principal detectado.
     *
     * @param string[] $setValido   Lista de códigos sugeridos para esta IPRESS
     */
    private function emitirSugerencia(string $pk, array $filasPrincipal, array $setValido, string $codActual): array
    {
        $sugerencia = implode(' + ', $setValido);
        $f          = $filasPrincipal[0]; // sugerencia solo en la primera aparición

        return [new Observacion(
            fila:        $f['fila'],
            pk:          $pk,
            codigo:      $codActual,
            valor:       $f['valor'],
            reglaCodigo: 'SUGERENCIA',
            reglaNombre: 'Sugerencia hemograma',
            prioridad:   $this->prioridadSug,
            color:       $this->colorSug,
            motivo:      "Considerar {$sugerencia} (representación válida para esta IPRESS) en lugar de {$codActual}",
            accion:      'SUGERENCIA',
        )];
    }
}
