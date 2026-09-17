<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Regla genérica parametrizable para grupos de códigos redundantes.
 *
 * Si una atención contiene 2 o más códigos DISTINTOS del grupo, conserva uno
 * y marca los demás con ELIMINAR.  El código conservado es el preferente,
 * si se configuró y está presente; si no, el de mayor valor económico.
 *
 * Se instancia para Urocultivo (mayor valor) y Coagulación (preferente 85345).
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
     * @param ?string  $codigoPreferente Código que se conserva siempre que esté
     *                                presente; null → conservar el de mayor valor
     */
    public function __construct(
        private readonly string  $codigoRegla,
        private readonly string  $nombreRegla,
        private readonly string  $colorHex,
        private readonly int     $prioridadVal,
        array                    $codigos,
        private readonly ?string $codigoPreferente = null,
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

        // El código a conservar: el preferente si está presente; si no, el de mayor valor
        if ($this->codigoPreferente !== null && isset($porCodigo[$this->codigoPreferente])) {
            $conservar = $this->codigoPreferente;
            $criterio  = 'código de referencia';
        } else {
            $maxPorCodigo = [];
            foreach ($porCodigo as $cod => $filas) {
                $maxPorCodigo[$cod] = max(array_map(
                    static fn(array $f): float => (float) ($f['valor'] ?? 0.0),
                    $filas
                ));
            }
            arsort($maxPorCodigo);
            $conservar = (string) array_key_first($maxPorCodigo);
            $criterio  = 'mayor valor';
        }

        // Marcar TODAS las filas de los códigos que NO se conservan.
        // PHP convierte las claves numéricas del array a int; comparar en string.
        $obs = [];
        foreach ($porCodigo as $cod => $filas) {
            if ((string) $cod === $conservar) {
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
                    motivo:      "{$this->nombreRegla}; conservar el código {$conservar} ({$criterio})",
                    accion:      'ELIMINAR',
                );
            }
        }

        return $obs;
    }
}
