<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Regla genérica parametrizable para grupos de códigos redundantes.
 *
 * Si una atención contiene 2 o más códigos DISTINTOS del grupo, conserva
 * el de mayor valor económico y marca los demás con ELIMINAR.
 *
 * Se instancia para Urocultivo — prioridad 2 / color turquesa.
 * (Hemograma usa su propia ReglaHemograma IPRESS-aware.)
 */
class ReglaRedundanciaGrupo implements ReglaInterface
{
    /** @var array<string,true>  Lookup O(1) de códigos del grupo */
    private array $lookup;

    /**
     * @param string   $codigoRegla   Identificador único ('UROCULTIVO', …)
     * @param string   $nombreRegla   Nombre legible para leyenda
     * @param string   $colorHex      Hex RGB sin # (p.ej. 'B7E1E4')
     * @param int      $prioridadVal  Mayor = más prioritario en conflicto
     * @param string[] $codigos       Códigos CPMS normalizados que forman el grupo
     */
    public function __construct(
        private readonly string $codigoRegla,
        private readonly string $nombreRegla,
        private readonly string $colorHex,
        private readonly int    $prioridadVal,
        array                   $codigos,
    ) {
        $this->lookup = array_fill_keys($codigos, true);
    }

    public function codigo(): string { return $this->codigoRegla; }
    public function nombre(): string { return $this->nombreRegla; }
    public function color(): string  { return $this->colorHex; }
    public function prioridad(): int { return $this->prioridadVal; }

    public function evaluar(string $pk, array $atencion): array
    {
        // Agrupar filas del grupo por código (código → lista de filas)
        $porCodigo = [];
        foreach ($atencion as $f) {
            if (isset($this->lookup[$f['codigo']])) {
                $porCodigo[$f['codigo']][] = $f;
            }
        }

        // La regla exige 2+ CÓDIGOS DISTINTOS del grupo.
        // Tener el mismo código varias veces es duplicado, no redundancia de grupo.
        if (count($porCodigo) < 2) {
            return [];
        }

        // Para cada código distinto, tomar el mayor valor que aparece en sus filas
        $maxPorCodigo = [];
        foreach ($porCodigo as $cod => $filas) {
            $maxPorCodigo[$cod] = max(array_map(
                static fn(array $f): float => (float) ($f['valor'] ?? 0.0),
                $filas
            ));
        }

        // El código a conservar: mayor valor máximo
        arsort($maxPorCodigo);
        $conservar = (string) array_key_first($maxPorCodigo);

        // Marcar TODAS las filas de los códigos que NO se conservan
        $obs = [];
        foreach ($porCodigo as $cod => $filas) {
            if ($cod === $conservar) {
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
                    prioridad:   $this->prioridad(),
                    color:       $this->color(),
                    motivo:      "Redundancia de {$this->nombreRegla}; conservar el código {$conservar} (mayor valor)",
                    accion:      'ELIMINAR',
                );
            }
        }

        return $obs;
    }
}
