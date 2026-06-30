<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Dentro de una atención (mismo PK) detecta códigos CPMS repetidos.
 *
 * Para cada código que aparece N ≥ 2 veces:
 *   - Primera ocurrencia → AGREGAR — cantidad = N  (consolidar en esta fila)
 *   - Ocurrencias siguientes → ELIMINAR (redundancia consolidada en la primera)
 *
 * Cubre automáticamente HbA1c (83036) y consejerías (1 por CPMS por prestación).
 *
 * Prioridad 4 / color violeta.
 */
class ReglaCodigosDuplicados implements ReglaInterface
{
    public function codigo(): string { return 'DUPLICADO'; }
    public function nombre(): string { return 'Códigos duplicados'; }
    public function color(): string  { return 'E8CCFF'; }
    public function prioridad(): int { return 4; }

    public function evaluar(string $pk, array $atencion): array
    {
        // Agrupar todas las filas por código (preserva orden de aparición)
        $grupos = []; // codigo => list<fila_array>
        foreach ($atencion as $f) {
            if ($f['codigo'] === '') {
                continue;
            }
            $grupos[(string) $f['codigo']][] = $f;
        }

        $obs = [];
        foreach ($grupos as $cod => $filas) {
            $n   = count($filas);
            $cod = (string) $cod; // PHP convierte claves numéricas a int; restaurar string
            if ($n < 2) {
                continue; // único → no es duplicado
            }

            $primeraFila = $filas[0]['fila'];

            // Primera ocurrencia: acción de consolidación
            $obs[] = new Observacion(
                fila:        $primeraFila,
                pk:          $pk,
                codigo:      $cod,
                valor:       $filas[0]['valor'],
                reglaCodigo: $this->codigo(),
                reglaNombre: $this->nombre(),
                prioridad:   $this->prioridad(),
                color:       $this->color(),
                motivo:      "Código {$cod} repetido {$n} veces; consolidar cantidad en esta fila",
                accion:      "AGREGAR — cantidad = {$n}",
            );

            // Ocurrencias siguientes: eliminar
            for ($i = 1; $i < $n; $i++) {
                $obs[] = new Observacion(
                    fila:        $filas[$i]['fila'],
                    pk:          $pk,
                    codigo:      $cod,
                    valor:       $filas[$i]['valor'],
                    reglaCodigo: $this->codigo(),
                    reglaNombre: $this->nombre(),
                    prioridad:   $this->prioridad(),
                    color:       $this->color(),
                    motivo:      "Repetición del código {$cod}; consolidado en la fila {$primeraFila}",
                    accion:      'ELIMINAR',
                );
            }
        }

        return $obs;
    }
}
