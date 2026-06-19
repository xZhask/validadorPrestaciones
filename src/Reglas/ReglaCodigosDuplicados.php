<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Dentro de una atención (mismo PK) conserva la primera ocurrencia de cada
 * COD. CPMS y marca todas las repeticiones con ELIMINAR X DUPLICIDAD.
 *
 * Prioridad 3 / color violeta.
 */
class ReglaCodigosDuplicados implements ReglaInterface
{
    public function codigo(): string  { return 'DUPLICADO'; }
    public function nombre(): string  { return 'Códigos duplicados'; }
    public function color(): string   { return 'E8CCFF'; }
    public function prioridad(): int  { return 3; }

    public function evaluar(string $pk, array $atencion): array
    {
        $obs    = [];
        // codigo => número de fila de la primera ocurrencia
        $vistos = [];

        foreach ($atencion as $f) {
            $cod = $f['codigo'];
            if ($cod === '') {
                continue;
            }

            if (!array_key_exists($cod, $vistos)) {
                $vistos[$cod] = $f['fila'];
            } else {
                $obs[] = new Observacion(
                    fila:        $f['fila'],
                    pk:          $pk,
                    codigo:      $cod,
                    valor:       $f['valor'],
                    reglaCodigo: $this->codigo(),
                    reglaNombre: $this->nombre(),
                    prioridad:   $this->prioridad(),
                    color:       $this->color(),
                    motivo:      "Código {$cod} ya figura en la fila {$vistos[$cod]} de esta atención",
                    accion:      'ELIMINAR X DUPLICIDAD',
                );
            }
        }

        return $obs;
    }
}
