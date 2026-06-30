<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;
use Validador\Texto;

/**
 * Regla de hemograma IPRESS-aware (v2).
 *
 * Familia de códigos hemograma (configurable):
 *   85025, 85027, 85007, 85013, 85014, 85018, 85032, 85049, 85590
 *
 * Representación válida por IPRESS (configurable vía config.php):
 *   Arequipa / Chiclayo → par {85027, 85007}
 *   ABL / Geriátrico    → {85025}
 *
 * Lógica por prestación:
 *   1. Si algún código del conjunto válido V está presente → conservarlo
 *      (y su par si V es un par y ambos están presentes).
 *      Eliminar todo otro código de la familia.
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

    /** [clave_texto_normalizado => list<string>] */
    private array $ipressMapeo;

    /** Códigos CBC de respaldo cuando ningún código de V está presente */
    private const CBC_FALLBACK = ['85025', '85027'];

    public function __construct(
        private readonly string $colorElim,
        private readonly int    $prioridadElim,
        private readonly string $colorSug,
        private readonly int    $prioridadSug,
        array                   $codigos,
        array                   $ipressMapeo,
    ) {
        $this->familia     = array_fill_keys($codigos, true);
        $this->ipressMapeo = $ipressMapeo;
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

        foreach ($atencion as $f) {
            if ($ipressNom === '' && $f['ipress_nom'] !== '') {
                $ipressNom = $f['ipress_nom'];
            }
            if (isset($this->familia[$f['codigo']])) {
                $presentes[$f['codigo']][] = $f;
            }
        }

        if (empty($presentes)) {
            return [];
        }

        // 2. Determinar el conjunto válido para esta IPRESS
        $claveIpress = Texto::clave($ipressNom);
        $setValido   = $this->ipressMapeo[$claveIpress] ?? null;

        // 3. Aplicar lógica
        if ($setValido === null) {
            // IPRESS no mapeada: conservar el de mayor valor, eliminar el resto
            return $this->eliminarMenosMaxValor($pk, $presentes);
        }

        $setLookup       = array_fill_keys($setValido, true);
        $codigosValidos  = array_filter(array_keys($presentes), fn(string $c) => isset($setLookup[$c]));

        if (!empty($codigosValidos)) {
            // Caso A: hay al menos un código del set válido presente → conservar todos los del set
            $conservar = array_fill_keys($codigosValidos, true);
            return $this->eliminarResto($pk, $presentes, $conservar, $codigosValidos);
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
