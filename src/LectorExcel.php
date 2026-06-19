<?php

declare(strict_types=1);

namespace Validador;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LectorExcel
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/config.php';
    }

    /**
     * Carga el libro, detecta la hoja, mapea columnas y agrupa por PK.
     *
     * @return array{
     *   spreadsheet: Spreadsheet,
     *   sheet: Worksheet,
     *   colMap: array<string,int>,
     *   colCount: int,
     *   highestRow: int,
     *   atenciones: array<string, list<array{fila:int,codigo:string,tipo:string,desc:string,valor:float|null}>>
     * }
     */
    public function cargar(string $ruta): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $sheet       = $this->detectarHoja($spreadsheet);

        // Leer como array 0-based (sin calcular fórmulas, sin formatear)
        $raw = $sheet->toArray(null, false, false, false);

        if (empty($raw)) {
            throw new \RuntimeException(
                "La hoja \"{$this->cfg['hoja']}\" está vacía."
            );
        }

        $highestRow = count($raw);
        $colCount   = !empty($raw[0]) ? count($raw[0]) : 0;

        if ($colCount === 0) {
            throw new \RuntimeException(
                "La hoja \"{$this->cfg['hoja']}\" no contiene columnas."
            );
        }

        // ── Mapeo de encabezados → índice de columna (1-based) ────────────
        $colMap = $this->mapearColumnas($raw[0] ?? []);

        // ── Índices de las columnas de interés ────────────────────────────
        $colCfg  = $this->cfg['columnas'];
        $iPk     = $this->buscarCol($colMap, $colCfg['pk']);
        $iCodigo = $this->buscarCol($colMap, $colCfg['codigo']);
        $iTipo   = $this->buscarCol($colMap, $colCfg['tipo']);
        $iDesc   = $this->buscarCol($colMap, $colCfg['desc']);
        $iValor  = $this->buscarCol($colMap, $colCfg['valor']);

        if ($iPk === null) {
            throw new \RuntimeException("No se encontró la columna PK en el archivo.");
        }
        if ($iCodigo === null) {
            throw new \RuntimeException("No se encontró la columna COD. CPMS en el archivo.");
        }

        // ── Agrupar filas por PK ──────────────────────────────────────────
        // $raw es 0-based; la fila Excel real = índice + 1
        $atenciones = [];
        for ($i = 1; $i < $highestRow; $i++) {
            $fila = $i + 1; // número de fila Excel (1-based, fila 1 = encabezado)
            $pk   = trim((string) ($raw[$i][$iPk - 1] ?? ''));
            if ($pk === '') {
                continue;
            }

            $atenciones[$pk][] = [
                'fila'   => $fila,
                'codigo' => Normalizador::codigo($raw[$i][$iCodigo - 1] ?? null),
                'tipo'   => (string) ($iTipo   !== null ? ($raw[$i][$iTipo   - 1] ?? '') : ''),
                'desc'   => (string) ($iDesc   !== null ? ($raw[$i][$iDesc   - 1] ?? '') : ''),
                'valor'  => $iValor !== null && $raw[$i][$iValor - 1] !== null
                                ? (float) $raw[$i][$iValor - 1]
                                : null,
            ];
        }

        if (empty($atenciones)) {
            throw new \RuntimeException(
                "No se encontraron filas de datos en la hoja \"{$this->cfg['hoja']}\". " .
                "Verifique que el archivo tenga datos debajo del encabezado y que la columna PK no esté vacía."
            );
        }

        return [
            'spreadsheet' => $spreadsheet,
            'sheet'       => $sheet,
            'colMap'      => $colMap,
            'colCount'    => $colCount,
            'highestRow'  => $highestRow,
            'atenciones'  => $atenciones,
        ];
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    private function detectarHoja(Spreadsheet $spreadsheet): Worksheet
    {
        $nombre = $this->cfg['hoja'];
        $sheet  = $spreadsheet->getSheetByName($nombre);
        if ($sheet === null) {
            // Intentar con clave normalizada
            foreach ($spreadsheet->getSheetNames() as $n) {
                if (Texto::clave($n) === Texto::clave($nombre)) {
                    return $spreadsheet->getSheetByName($n);
                }
            }
            throw new \RuntimeException(
                "No se encontró la hoja \"{$nombre}\". " .
                "Hojas disponibles: " . implode(', ', $spreadsheet->getSheetNames())
            );
        }
        return $sheet;
    }

    /**
     * Construye mapa [clave_normalizada => índice_1based].
     */
    private function mapearColumnas(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $ci => $header) {
            $clave = Texto::clave((string) ($header ?? ''));
            if ($clave !== '') {
                $map[$clave] = $ci + 1; // convertir a 1-based
            }
        }
        return $map;
    }

    /**
     * Busca el índice 1-based de una columna por su nombre de config.
     * Devuelve null si no existe.
     */
    private function buscarCol(array $colMap, string $nombreCfg): ?int
    {
        return $colMap[Texto::clave($nombreCfg)] ?? null;
    }

    // ── Acceso estático cómodo para reglas y escritor ─────────────────────

    /**
     * Devuelve el índice 1-based de una columna dentro de los datos cargados.
     */
    public static function indiceCol(array $datos, string $nombreCfg): ?int
    {
        return $datos['colMap'][Texto::clave($nombreCfg)] ?? null;
    }
}
