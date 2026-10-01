<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Dentro de una atención (mismo PK) detecta códigos CPMS repetidos.
 *
 * Para cada código que aparece en N ≥ 2 filas:
 *   - Primera ocurrencia → AGREGAR — cantidad = T  (consolidar en esta fila)
 *   - Ocurrencias siguientes → ELIMINAR (redundancia consolidada en la primera)
 *
 * T es la suma de la columna cantidad de esas filas, no el número de filas:
 * una fila que ya trae cantidad 3 aporta 3.  Así coincide con ReglaHemograma,
 * que también suma cantidades al comparar los códigos del par.  Una fila sin
 * cantidad cuenta como 1, porque estar facturada ya es al menos una unidad.
 *
 * Cubre automáticamente HbA1c (83036) y consejerías (1 por CPMS por prestación).
 *
 * Color y prioridad vienen de config.php (ELIMINAR_DUPLICADO: violeta, 4).
 */
class ReglaCodigosDuplicados implements ReglaInterface
{
    public const CODIGO = 'DUPLICADO';

    public function __construct(
        private readonly string $colorHex,
        private readonly int    $prioridadVal,
    ) {}

    public function codigo(): string { return self::CODIGO; }
    public function nombre(): string { return 'Códigos duplicados'; }
    public function color(): string  { return $this->colorHex; }
    public function prioridad(): int { return $this->prioridadVal; }

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
            $total       = array_sum(array_map(
                static fn(array $f): int => ($f['cantidad'] ?? 0) > 0 ? (int) $f['cantidad'] : 1,
                $filas
            ));
            $motivo = $total === $n
                ? "Código {$cod} repetido {$n} veces; consolidar cantidad en esta fila"
                : "Código {$cod} repetido en {$n} filas que suman cantidad {$total}; consolidar cantidad en esta fila";

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
                motivo:      $motivo,
                accion:      "AGREGAR — cantidad = {$total}",
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
