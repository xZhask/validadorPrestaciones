<?php

declare(strict_types=1);

namespace Validador\Reglas;

use Validador\Observacion;

/**
 * Contrato que debe implementar cada regla de negocio.
 *
 * El motor llama a evaluar() una vez por atención (grupo de filas con el mismo PK).
 * La regla recibe todas las filas de esa atención y devuelve las Observaciones
 * que considera pertinentes (0 si la atención no incumple la regla).
 */
interface ReglaInterface
{
    /** Identificador único de la regla (p.ej. "DUPLICADO", "HEMOGRAMA"). */
    public function codigo(): string;

    /** Nombre legible para la leyenda y el resumen (p.ej. "Códigos duplicados"). */
    public function nombre(): string;

    /**
     * Color de fondo de fila para las filas que esta regla marca (hex RGB sin #).
     * Cuando varias reglas afectan a la misma fila, gana la de mayor prioridad.
     */
    public function color(): string;

    /**
     * Prioridad de la regla.  Valor mayor = más importante.
     * Se usa para resolver el color y la acción cuando una fila cae
     * en varias reglas simultáneamente.
     */
    public function prioridad(): int;

    /**
     * Evalúa una atención completa y devuelve las observaciones detectadas.
     *
     * @param string $pk         Identificador de la atención.
     * @param list<array{
     *   fila:   int,
     *   codigo: string,
     *   tipo:   string,
     *   desc:   string,
     *   valor:  float|null
     * }> $atencion  Filas que componen la atención, tal como las entrega LectorExcel.
     *
     * @return list<Observacion>  Vacío si la atención no incumple la regla.
     */
    public function evaluar(string $pk, array $atencion): array;
}
