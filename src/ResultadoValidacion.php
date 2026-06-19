<?php

declare(strict_types=1);

namespace Validador;

/**
 * Agrega todas las Observaciones producidas por el motor y expone
 * vistas derivadas que necesitan el escritor y la interfaz web.
 */
class ResultadoValidacion
{
    /** @var list<Observacion> */
    private array $observaciones = [];

    /** Índice fila → observaciones; se construye la primera vez que se consulta. */
    private ?array $indiceFilas = null;

    // ── Construcción ─────────────────────────────────────────────────────

    public function agregar(Observacion $obs): void
    {
        $this->observaciones[] = $obs;
        $this->indiceFilas     = null; // invalida caché
    }

    // ── Consultas ─────────────────────────────────────────────────────────

    public function totalObservaciones(): int
    {
        return count($this->observaciones);
    }

    public function vacio(): bool
    {
        return empty($this->observaciones);
    }

    /** @return list<Observacion> */
    public function todas(): array
    {
        return $this->observaciones;
    }

    /**
     * Todas las observaciones agrupadas por número de fila.
     *
     * @return array<int, list<Observacion>>
     */
    public function porFila(): array
    {
        if ($this->indiceFilas === null) {
            $this->indiceFilas = [];
            foreach ($this->observaciones as $obs) {
                $this->indiceFilas[$obs->fila][] = $obs;
            }
        }
        return $this->indiceFilas;
    }

    /**
     * Resolución final por fila para el escritor Excel y la tabla web:
     *   – acción  : de la regla de mayor prioridad
     *   – motivo  : todos los motivos concatenados con " || "
     *   – color   : de la regla de mayor prioridad
     *
     * Solo contiene filas que tienen al menos una observación.
     *
     * @return array<int, array{accion:string, motivo:string, color:string}>
     */
    public function resolucionPorFila(): array
    {
        $resolucion = [];

        foreach ($this->porFila() as $fila => $lista) {
            // Ordenar por prioridad DESC para que el [0] sea el dominante
            usort($lista, static fn(Observacion $a, Observacion $b) =>
                $b->prioridad <=> $a->prioridad
            );

            $dominante = $lista[0];

            // Motivos únicos en orden de prioridad, sin repetición de texto
            $motivos = [];
            foreach ($lista as $obs) {
                if (!in_array($obs->motivo, $motivos, true)) {
                    $motivos[] = $obs->motivo;
                }
            }

            $resolucion[$fila] = [
                'accion' => $dominante->accion,
                'motivo' => implode(' || ', $motivos),
                'color'  => $dominante->color,
            ];
        }

        return $resolucion;
    }

    /**
     * Resumen por regla: útil para la leyenda y las métricas de la UI.
     *
     * @return array<string, array{
     *   nombre: string,
     *   color: string,
     *   prioridad: int,
     *   filas: int,
     *   atenciones: int
     * }>
     */
    public function resumenPorRegla(): array
    {
        $resumen = [];

        foreach ($this->observaciones as $obs) {
            $rc = $obs->reglaCodigo;

            if (!isset($resumen[$rc])) {
                $resumen[$rc] = [
                    'nombre'      => $obs->reglaNombre,
                    'color'       => $obs->color,
                    'prioridad'   => $obs->prioridad,
                    'filas'       => 0,
                    'atenciones'  => 0,
                    // conjuntos internos para deduplicar
                    '_filas'      => [],
                    '_pks'        => [],
                ];
            }

            // isset sobre clave es O(1) — sin in_array
            if (!isset($resumen[$rc]['_filas'][$obs->fila])) {
                $resumen[$rc]['_filas'][$obs->fila] = true;
                $resumen[$rc]['filas']++;
            }
            if (!isset($resumen[$rc]['_pks'][$obs->pk])) {
                $resumen[$rc]['_pks'][$obs->pk] = true;
                $resumen[$rc]['atenciones']++;
            }
        }

        // Limpiar conjuntos internos antes de devolver
        foreach ($resumen as &$r) {
            unset($r['_filas'], $r['_pks']);
        }
        unset($r);

        // Ordenar de mayor a menor prioridad
        uasort($resumen, static fn($a, $b) => $b['prioridad'] <=> $a['prioridad']);

        return $resumen;
    }
}
