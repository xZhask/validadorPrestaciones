<?php

declare(strict_types=1);

namespace Validador;

/**
 * Value Object inmutable que representa una observación sobre una fila concreta.
 * Una misma fila puede acumular varias Observaciones (de distintas reglas);
 * ResultadoValidacion resuelve el color y la acción final por precedencia.
 */
final class Observacion
{
    public function __construct(
        /** Número de fila en el libro Excel (2-based; fila 1 = encabezado). */
        public readonly int        $fila,

        /** PK de la atención a la que pertenece la fila. */
        public readonly string     $pk,

        /** COD. CPMS normalizado de la fila observada. */
        public readonly string     $codigo,

        /** Valor económico de la prestación (null si la celda estaba vacía). */
        public readonly float|null $valor,

        /** Identificador único de la regla que generó esta observación. */
        public readonly string     $reglaCodigo,

        /** Nombre legible de la regla (para leyenda y resumen). */
        public readonly string     $reglaNombre,

        /**
         * Prioridad numérica de la regla.
         * Cuando una fila cae en varias reglas, se usa el color y la acción
         * de la regla con mayor prioridad.
         */
        public readonly int        $prioridad,

        /** Color de fondo de fila (hex RGB sin #, p.ej. "FFCCCC"). */
        public readonly string     $color,

        /** Texto explicativo del problema detectado. */
        public readonly string     $motivo,

        /** Acción sugerida al auditor ("ELIMINAR", "REVISAR", etc.). */
        public readonly string     $accion,
    ) {}
}
