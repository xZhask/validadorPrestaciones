<?php

declare(strict_types=1);

namespace Validador;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
