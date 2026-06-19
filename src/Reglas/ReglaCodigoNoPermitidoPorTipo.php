<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Regla genérica parametrizable: marca cualquier fila donde el COD. CPMS
 * especificado aparezca en un TIPO DE ATENCIÓN prohibido.
 *
 * Instancia canónica en index.php:
 *   código 93784 en tipos 2 y 3 → ELIMINAR — prioridad 4 / color rojo.
 */
class ReglaCodigoNoPermitidoPorTipo implements ReglaInterface
{
    /** @var array<string,true>  Tipos prohibidos como lookup O(1) */
    private array $tiposLookup;

    /**
     * @param string   $codigoRegla     Identificador de la regla ('PROHIBIDO_93784', …)
     * @param string   $nombreRegla     Nombre legible para leyenda
     * @param string   $color           Hex RGB sin #
     * @param int      $prioridad       Mayor = más prioritario
     * @param string   $codigoCpms      COD. CPMS normalizado prohibido
     * @param string[] $tiposProhibidos Valores de TIPO DE ATENCIÓN en que está prohibido
     * @param string   $accion          Texto de ACCIÓN SUGERIDA ('ELIMINAR', …)
     */
    public function __construct(
        private readonly string $codigoRegla,
        private readonly string $nombreRegla,
        private readonly string $colorHex,
        private readonly int    $prioridadVal,
        private readonly string $codigoCpms,
        array                   $tiposProhibidos,
        private readonly string $accionTexto,
    ) {
        $this->tiposLookup = array_fill_keys(
            array_map('strval', $tiposProhibidos),
            true
        );
    }

    public function codigo(): string { return $this->codigoRegla; }
    public function nombre(): string { return $this->nombreRegla; }
    public function color(): string  { return $this->colorHex; }
    public function prioridad(): int { return $this->prioridadVal; }

    public function evaluar(string $pk, array $atencion): array
    {
        $obs = [];

        foreach ($atencion as $f) {
            if ($f['codigo'] !== $this->codigoCpms) {
                continue;
            }
            if (!isset($this->tiposLookup[(string) $f['tipo']])) {
                continue;
            }

            $obs[] = new Observacion(
                fila:        $f['fila'],
                pk:          $pk,
                codigo:      $f['codigo'],
                valor:       $f['valor'],
                reglaCodigo: $this->codigo(),
                reglaNombre: $this->nombre(),
                prioridad:   $this->prioridad(),
                color:       $this->color(),
                motivo:      "Código {$this->codigoCpms} no permitido en TIPO DE ATENCIÓN {$f['tipo']}",
                accion:      $this->accionTexto,
            );
        }

        return $obs;
    }
}
