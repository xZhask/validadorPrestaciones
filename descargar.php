<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Validador\EscritorExcel;
use Validador\GestorSesiones;

$cfg = require __DIR__ . '/src/config.php';

// ── Modo 1: descarga por ID de sesión ─────────────────────────────────────
$id = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['id'] ?? ''));

if (strlen($id) === 32) {
    ini_set('memory_limit', $cfg['limites']['memory']);
    set_time_limit($cfg['limites']['timeout']);

    try {
        $gestor   = new GestorSesiones($cfg['storage_dir']);
        $estado   = $gestor->cargar($id);
        $rutaOrig = $gestor->rutaOriginal($id);

        if (!file_exists($rutaOrig)) {
            http_response_code(404);
            exit('Archivo original de la sesión no encontrado.');
        }

        $escritor = new EscritorExcel();
        $tmpFile  = $escritor->generarDesdeSession($rutaOrig, $estado);

        $nombreBase = $estado['archivo'] ?? "archivo_{$id}.xlsx";
        $nombre     = 'validado_' . pathinfo($nombreBase, PATHINFO_FILENAME) . '.xlsx';
        $nombre     = preg_replace('/[^\w\s\.\-\_\(\)]/u', '_', $nombre);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($nombre) . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache, no-store');
        header('Pragma: no-cache');

        readfile($tmpFile);

        register_shutdown_function(static function () use ($tmpFile): void {
            @unlink($tmpFile);
        });
        exit;

    } catch (\Throwable $e) {
        http_response_code(500);
        exit('Error generando el archivo: ' . htmlspecialchars($e->getMessage()));
    }
}

// ── Modo 2: descarga por token (compatibilidad v1) ────────────────────────
$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['token'] ?? ''));

if (strlen($token) !== 32) {
    http_response_code(400);
    exit('Parámetro inválido. Usa ?id=&lt;sesión&gt; o ?token=&lt;token&gt;.');
}

$dir        = $cfg['storage_dir'];
$rutaXlsx   = "{$dir}/{$token}.xlsx";
$rutaNombre = "{$dir}/{$token}.name";

if (!file_exists($rutaXlsx)) {
    http_response_code(404);
    exit('Archivo no encontrado o ya descargado.');
}

$nombre = file_exists($rutaNombre)
    ? trim(file_get_contents($rutaNombre))
    : "validado_{$token}.xlsx";
$nombre = preg_replace('/[^\w\s\.\-\_\(\)]/u', '_', $nombre);

register_shutdown_function(static function () use ($dir, $cfg): void {
    $limite = time() - $cfg['token_ttl'];
    foreach (glob("{$dir}/*.xlsx") as $f) {
        if (filemtime($f) < $limite) {
            @unlink($f);
            @unlink(substr($f, 0, -5) . '.name');
        }
    }
});

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . rawurlencode($nombre) . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('Content-Length: ' . filesize($rutaXlsx));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

readfile($rutaXlsx);

@unlink($rutaXlsx);
@unlink($rutaNombre);
