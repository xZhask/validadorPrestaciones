<?php

declare(strict_types=1);

$cfg = require __DIR__ . '/src/config.php';

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['token'] ?? ''));

if (strlen($token) !== 32) {
    http_response_code(400);
    exit('Token inválido.');
}

$dir      = $cfg['storage_dir'];
$rutaXlsx = "{$dir}/{$token}.xlsx";
$rutaNombre = "{$dir}/{$token}.name";

if (!file_exists($rutaXlsx)) {
    http_response_code(404);
    exit('Archivo no encontrado o ya descargado.');
}

// Nombre original conservado por EscritorExcel
$nombre = file_exists($rutaNombre)
    ? trim(file_get_contents($rutaNombre))
    : "validado_{$token}.xlsx";

// Sanitizar por si acaso
$nombre = preg_replace('/[^\w\s\.\-\_\(\)]/u', '_', $nombre);

// Limpiar temporales vencidos de este mismo request (no bloquear la entrega)
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

// Eliminar tras la primera descarga
@unlink($rutaXlsx);
@unlink($rutaNombre);
