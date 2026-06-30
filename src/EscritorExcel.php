<?php

declare(strict_types=1);

namespace Validador;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EscritorExcel
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/config.php';
    }

    /**
     * Agrega ACCIÓN SUGERIDA y MOTIVO DE OBSERVACIÓN al libro ya cargado,
     * pinta las filas con el color de la regla de mayor precedencia y
     * guarda el resultado en storage/.
     *
     * @param  array               $datos          Resultado de LectorExcel::cargar()
     * @param  ResultadoValidacion $resultado      Resultado del MotorValidacion
     * @param  string              $nombreOriginal Nombre del archivo subido por el usuario
     * @return array{token: string, nombre: string}
     */
    public function escribir(
        array               $datos,
        ResultadoValidacion $resultado,
        string              $nombreOriginal
    ): array {
        $spreadsheet = $datos['spreadsheet'];
        $sheet       = $datos['sheet'];
        $colCount    = $datos['colCount'];
        $highestRow  = $datos['highestRow'];

        // ── Letras de las nuevas columnas ─────────────────────────────────
        $letAccion = Coordinate::stringFromColumnIndex($colCount + 1);
        $letMotivo = Coordinate::stringFromColumnIndex($colCount + 2);

        // ── Encabezados ───────────────────────────────────────────────────
        $sheet->getCell($letAccion . '1')->setValue('ACCIÓN SUGERIDA');
        $sheet->getCell($letMotivo . '1')->setValue('MOTIVO DE OBSERVACIÓN');

        $this->estiloEncabezado($sheet, $letAccion . '1');
        $this->estiloEncabezado($sheet, $letMotivo . '1');

        $sheet->getColumnDimension($letAccion)->setWidth(28);
        $sheet->getColumnDimension($letMotivo)->setWidth(60);

        // ── Datos y colores fila a fila ───────────────────────────────────
        // Pintamos solo las 2 columnas nuevas (7 150 filas × 2 = 14 300 ops de estilo).
        // Pintar las 41 columnas de la fila implicaría ~293 K ops y ~100 s de escritura.
        foreach ($resultado->resolucionPorFila() as $r => $res) {
            $sheet->getCell($letAccion . $r)->setValue($res['accion']);
            $sheet->getCell($letMotivo  . $r)->setValue($res['motivo']);

            // Color en celda ACCIÓN
            $sheet->getStyle("{$letAccion}{$r}")
                  ->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()
                  ->setRGB($res['color']);

            // Color + wrapText en celda MOTIVO (una sola llamada applyFromArray)
            $sheet->getStyle("{$letMotivo}{$r}")->applyFromArray([
                'fill'      => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $res['color']],
                ],
                'alignment' => ['wrapText' => true],
            ]);
        }

        // ── Guardar en storage/ ───────────────────────────────────────────
        $this->limpiarStorage();

        $dir = $this->cfg['storage_dir'];

        if (!is_dir($dir)) {
            throw new \RuntimeException(
                "El directorio de almacenamiento no existe: {$dir}. " .
                "Créalo con: mkdir storage"
            );
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException(
                "El directorio de almacenamiento no tiene permisos de escritura: {$dir}."
            );
        }

        $token        = bin2hex(random_bytes(16));
        $nombreSalida = 'validado_' . pathinfo($nombreOriginal, PATHINFO_FILENAME) . '.xlsx';

        // Archivo con el nombre deseado para Content-Disposition en descargar.php
        file_put_contents("{$dir}/{$token}.name", $nombreSalida);

        (new Xlsx($spreadsheet))->save("{$dir}/{$token}.xlsx");

        return ['token' => $token, 'nombre' => $nombreSalida];
    }

    /**
     * Genera el Excel de salida a partir del original.xlsx y el estado.json
     * de una sesión, sin necesitar el ResultadoValidacion en memoria.
     *
     * Devuelve la ruta del archivo temporal generado; el llamador debe
     * eliminarlo tras transmitirlo al cliente.
     */
    public function generarDesdeSession(string $rutaOriginal, array $estado): string
    {
        $spreadsheet = IOFactory::load($rutaOriginal);
        $hoja        = $this->cfg['hoja'] ?? 'DATA';
        $sheet       = $spreadsheet->getSheetByName($hoja) ?? $spreadsheet->getActiveSheet();

        $colIndex  = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $letAccion = Coordinate::stringFromColumnIndex($colIndex + 1);
        $letMotivo = Coordinate::stringFromColumnIndex($colIndex + 2);

        $sheet->getCell($letAccion . '1')->setValue('ACCIÓN SUGERIDA');
        $sheet->getCell($letMotivo . '1')->setValue('MOTIVO DE OBSERVACIÓN');
        $this->estiloEncabezado($sheet, $letAccion . '1');
        $this->estiloEncabezado($sheet, $letMotivo . '1');
        $sheet->getColumnDimension($letAccion)->setWidth(28);
        $sheet->getColumnDimension($letMotivo)->setWidth(60);

        foreach ($this->resolverObservacionesEstado($estado) as $fila => $res) {
            $sheet->getCell($letAccion . $fila)->setValue($res['accion']);
            $sheet->getCell($letMotivo  . $fila)->setValue($res['motivo']);

            $sheet->getStyle("{$letAccion}{$fila}")
                  ->getFill()->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setRGB($res['color']);

            $sheet->getStyle("{$letMotivo}{$fila}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $res['color']]],
                'alignment' => ['wrapText' => true],
            ]);
        }

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'validador_' . bin2hex(random_bytes(8)) . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $tmp;
    }

    /**
     * Reconstruye la resolución fila→{accion, motivo, color} desde el estado JSON.
     *
     * Replica la lógica de ResultadoValidacion::resolucionPorFila():
     * - accion y color vienen de la observación de mayor prioridad en la fila
     * - motivo es la concatenación única de todos los motivos (orden de prioridad DESC)
     *
     * @return array<int, array{accion:string, motivo:string, color:string}>
     */
    private function resolverObservacionesEstado(array $estado): array
    {
        $porFila = [];

        foreach ($estado['prestaciones'] as $prestacion) {
            foreach (($prestacion['observaciones'] ?? []) as $fila => $listaObs) {
                $fila = (int) $fila;

                // Ordenar por prioridad DESC para que [0] sea el dominante
                usort($listaObs, static fn(array $a, array $b): int =>
                    (int) ($b['prioridad'] ?? 0) <=> (int) ($a['prioridad'] ?? 0)
                );

                if (!isset($porFila[$fila])) {
                    $porFila[$fila] = [
                        'prioridad' => (int) ($listaObs[0]['prioridad'] ?? 0),
                        'accion'    => $listaObs[0]['accion'],
                        'color'     => $listaObs[0]['color'] ?? 'CCCCCC',
                        'motivos'   => [],
                    ];
                } elseif ((int) ($listaObs[0]['prioridad'] ?? 0) > $porFila[$fila]['prioridad']) {
                    $porFila[$fila]['prioridad'] = (int) ($listaObs[0]['prioridad'] ?? 0);
                    $porFila[$fila]['accion']    = $listaObs[0]['accion'];
                    $porFila[$fila]['color']     = $listaObs[0]['color'] ?? 'CCCCCC';
                }

                foreach ($listaObs as $obs) {
                    if (!in_array($obs['motivo'], $porFila[$fila]['motivos'], true)) {
                        $porFila[$fila]['motivos'][] = $obs['motivo'];
                    }
                }
            }
        }

        $result = [];
        foreach ($porFila as $fila => $r) {
            $result[$fila] = [
                'accion' => $r['accion'],
                'motivo' => implode(' || ', $r['motivos']),
                'color'  => $r['color'],
            ];
        }
        ksort($result);
        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function estiloEncabezado(Worksheet $sheet, string $celda): void
    {
        $sheet->getStyle($celda)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E4057'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM],
            ],
        ]);
    }

    private function limpiarStorage(): void
    {
        $dir = $this->cfg['storage_dir'];
        $ttl = $this->cfg['token_ttl'];
        $limite = time() - $ttl;

        foreach (glob("{$dir}/*.xlsx") as $f) {
            if (filemtime($f) < $limite) {
                @unlink($f);
                @unlink(substr($f, 0, -5) . '.name');
            }
        }
    }
}
