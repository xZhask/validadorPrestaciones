<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Validador\GestorSesiones;
use Validador\LectorExcel;
use Validador\Logger;

$cfg = require __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/construirMotor.php';

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    Logger::error("PHP error [{$errno}]: {$errstr} en {$errfile}:{$errline}");
    return false;
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error('Fatal PHP error: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
    }
});

// ── Limpieza periódica de storage/ (se ejecuta en cada carga de página) ───
function limpiarStoragePasivo(string $dir, int $ttl): void
{
    if (!is_dir($dir)) return;
    $limite = time() - $ttl;
    foreach (glob("{$dir}/*.xlsx") as $f) {
        if (filemtime($f) < $limite) {
            @unlink($f);
            @unlink(substr($f, 0, -5) . '.name');
        }
    }
}

limpiarStoragePasivo($cfg['storage_dir'], $cfg['token_ttl']);

ini_set('memory_limit', $cfg['limites']['memory']);
set_time_limit($cfg['limites']['timeout']);

// ── Mensajes de error de subida ────────────────────────────────────────────
const UPLOAD_ERRORES = [
    UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo permitido por el servidor (upload_max_filesize).',
    UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo del formulario.',
    UPLOAD_ERR_PARTIAL    => 'La subida fue interrumpida. Intenta de nuevo.',
    UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
    UPLOAD_ERR_NO_TMP_DIR => 'Error de configuración del servidor: no hay directorio temporal disponible.',
    UPLOAD_ERR_CANT_WRITE => 'Error al guardar el archivo temporal en el servidor.',
    UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP interrumpió la subida del archivo.',
];

// ── Estado de la solicitud ─────────────────────────────────────────────────
$error            = null;
$aviso            = null;
$nombreSalida     = null;
$resultado        = null;
$sinObservaciones = false;
$totalAtenciones  = 0;
$resumenReglas    = [];
$tablaFilas       = [];
$totalFilasObs    = 0;
$maxTabla         = $cfg['limites']['max_filas_tabla'];
$truncado         = false;
$sesionId         = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['archivo'])) {

    $file = $_FILES['archivo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = UPLOAD_ERRORES[$file['error']]
            ?? 'Error desconocido al subir el archivo (código ' . (int) $file['error'] . ').';

    } elseif (!preg_match('/\.xlsx$/i', $file['name'])) {
        $error = 'Solo se aceptan archivos .xlsx. El archivo seleccionado no tiene esa extensión.';

    } elseif ($file['size'] === 0) {
        $error = 'El archivo está vacío (0 bytes).';

    } else {
        try {
            // Crear sesión de auditoría antes de procesar
            $gestor   = new GestorSesiones($cfg['storage_dir']);
            $sesionId = $gestor->crear($file['tmp_name'], $file['name']);

            $lector    = new LectorExcel();
            $datos     = $lector->cargar($file['tmp_name']);
            $motor     = construirMotor($cfg);
            $resultado = $motor->validar($datos['atenciones']);

            $totalAtenciones  = count($datos['atenciones']);
            $sinObservaciones = $resultado->vacio();

            // Persistir observaciones del sistema en la sesión
            $estado   = $gestor->cargar($sesionId);
            $porFila  = $resultado->porFila();
            foreach ($porFila as $fila => $listaObs) {
                foreach ($listaObs as $obs) {
                    $estado['prestaciones'][$obs->pk]['observaciones'][(string) $fila][] = [
                        'regla'     => $obs->reglaCodigo,
                        'accion'    => $obs->accion,
                        'motivo'    => $obs->motivo,
                        'color'     => $obs->color,
                        'prioridad' => $obs->prioridad,
                        'origen'    => 'sistema',
                    ];
                }
            }
            $gestor->guardar($sesionId, $estado);
            unset($estado, $porFila, $datos);
            gc_collect_cycles();

            $nombreSalida = 'validado_' . pathinfo($file['name'], PATHINFO_FILENAME) . '.xlsx';

            if (!$sinObservaciones) {
                $resumenReglas = $resultado->resumenPorRegla();
                $resolucion    = $resultado->resolucionPorFila();
                $porFila       = $resultado->porFila();
                $totalFilasObs = count($resolucion);
                $truncado      = $totalFilasObs > $maxTabla;

                foreach ($resolucion as $fila => $res) {
                    if (count($tablaFilas) >= $maxTabla) break;
                    $dominant = null;
                    foreach ($porFila[$fila] as $o) {
                        if ($dominant === null || $o->prioridad > $dominant->prioridad) {
                            $dominant = $o;
                        }
                    }
                    $tablaFilas[] = [
                        'fila'        => $fila,
                        'pk'          => $dominant->pk,
                        'codigo'      => $dominant->codigo,
                        'reglaCodigo' => $dominant->reglaCodigo,
                        'reglaNombre' => $dominant->reglaNombre,
                        'color'       => $res['color'],
                        'accion'      => $res['accion'],
                        'motivo'      => $res['motivo'],
                        'search'      => mb_strtolower(
                            $dominant->pk . ' ' . $dominant->codigo . ' ' . $res['motivo'],
                            'UTF-8'
                        ),
                    ];
                }
            }

            Logger::info(sprintf(
                'Validación completada: %s — %d atenciones, %d con observaciones — sesión %s',
                $file['name'],
                $totalAtenciones,
                $totalFilasObs,
                $sesionId ?? 'sin-sesion',
            ));

        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Logger::error('Error procesando archivo: ' . ($_FILES['archivo']['name'] ?? 'desconocido'), $e);
        }
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador CPMS</title>
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
<header class="site-header">
    <h1>Validador CPMS</h1>
    <p>Sube el archivo de atenciones para generar el reporte de observaciones.
       &nbsp;·&nbsp; <a href="auditorias.php" style="color:inherit;text-decoration:underline">Ver auditorías</a></p>
</header>

<main class="container">

    <?php if ($error): ?>
    <div class="alert alert-error">
        <strong>No se pudo procesar el archivo.</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <section class="card upload-card">
        <h2>Seleccionar archivo</h2>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="drop-zone" id="dropZone">
                <span class="drop-icon">📂</span>
                <p id="dropLabel">Arrastra tu <strong>.xlsx</strong> aquí o haz clic para seleccionar</p>
                <div class="drop-requisitos">
                    <span class="req-label">Hoja:</span>
                    <span class="req-chip"><?= htmlspecialchars($cfg['hoja']) ?></span>
                    <span class="req-sep">&middot;</span>
                    <span class="req-label">Columnas requeridas:</span>
                    <?php foreach ($cfg['columnas'] as $col): ?>
                    <span class="req-chip"><?= htmlspecialchars($col) ?></span>
                    <?php endforeach; ?>
                </div>
                <input type="file" name="archivo" id="archivoInput" accept=".xlsx" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="btnProcesar" disabled>
                    Procesar archivo
                </button>
                <a href="?" class="btn btn-outline">Limpiar</a>
            </div>
        </form>
    </section>

    <?php if ($sesionId): ?>
    <div class="alert" style="background:#e8f4fd;border-left:4px solid #3b82f6;color:#1e40af;margin-bottom:0">
        Sesión de auditoría creada:
        <strong style="font-family:monospace"><?= htmlspecialchars($sesionId) ?></strong>
        &nbsp;·&nbsp;
        <a href="revisar.php?id=<?= htmlspecialchars($sesionId) ?>" style="color:#1e40af;font-weight:600">Revisar prestaciones →</a>
        &nbsp;·&nbsp;
        <a href="auditorias.php" style="color:#1e40af">Ver auditorías</a>
    </div>
    <?php endif; ?>

    <?php if ($sesionId && $sinObservaciones): ?>

    <section class="card card-clean">
        <div class="clean-inner">
            <span class="clean-icon">✅</span>
            <div>
                <h2>Sin observaciones</h2>
                <p>El archivo fue validado y no se detectaron incumplimientos de las reglas configuradas.
                   Se procesaron <strong><?= number_format($totalAtenciones) ?></strong> atenciones.</p>
            </div>
        </div>
        <a href="descargar.php?id=<?= htmlspecialchars($sesionId) ?>"
           class="btn btn-success" style="margin-top:1.25rem">
            ⬇&nbsp;Descargar <?= htmlspecialchars($nombreSalida ?? '') ?>
        </a>
    </section>

    <?php elseif ($sesionId && $resultado): ?>

    <section class="card download-card">
        <div class="download-inner">
            <div>
                <h2>Archivo listo</h2>
                <p>El Excel contiene las columnas ACCIÓN SUGERIDA y MOTIVO DE OBSERVACIÓN marcadas por regla.</p>
            </div>
            <a href="descargar.php?id=<?= htmlspecialchars($sesionId) ?>"
               class="btn btn-success btn-lg">
                ⬇&nbsp;Descargar <?= htmlspecialchars($nombreSalida ?? '') ?>
            </a>
        </div>
    </section>

    <div class="metrics-grid">
        <div class="metric-card">
            <span class="metric-val"><?= number_format($totalAtenciones) ?></span>
            <span class="metric-lbl">Atenciones procesadas</span>
        </div>
        <div class="metric-card metric-warn">
            <span class="metric-val"><?= number_format($totalFilasObs) ?></span>
            <span class="metric-lbl">Filas con observación</span>
        </div>
        <div class="metric-card metric-info">
            <span class="metric-val"><?= number_format($resultado->totalObservaciones()) ?></span>
            <span class="metric-lbl">Observaciones totales</span>
        </div>
        <div class="metric-card">
            <span class="metric-val"><?= count($resumenReglas) ?></span>
            <span class="metric-lbl">Reglas activadas</span>
        </div>
    </div>

    <section class="card">
        <h2>Resumen por regla</h2>
        <div class="legend-list">
            <?php foreach ($resumenReglas as $r): ?>
            <div class="legend-item">
                <span class="swatch" style="background:#<?= htmlspecialchars($r['color']) ?>"></span>
                <div class="legend-info">
                    <strong><?= htmlspecialchars($r['nombre']) ?></strong>
                    <span><?= number_format($r['filas']) ?> filas &middot; <?= number_format($r['atenciones']) ?> atenciones</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card card-table">
        <div class="section-head">
            <h2>Detalle de observaciones</h2>
            <span class="count-badge" id="filtroContador"></span>
        </div>

        <div class="filter-bar">
            <input type="search" id="busqueda" placeholder="Buscar PK, código o motivo…" autocomplete="off">
            <div class="chips" id="chipGroup">
                <button class="chip active" data-regla="">Todas</button>
                <?php foreach ($resumenReglas as $rc => $r): ?>
                <button class="chip" data-regla="<?= htmlspecialchars($rc) ?>"
                        style="--chip-c:#<?= htmlspecialchars($r['color']) ?>">
                    <?= htmlspecialchars($r['nombre']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($truncado): ?>
        <p class="table-note">
            ⚠ Se muestran las primeras <?= number_format($maxTabla) ?> filas de
            <?= number_format($totalFilasObs) ?> con observaciones.
            El archivo Excel descargado contiene todas.
        </p>
        <?php endif; ?>

        <div class="table-wrap">
            <table id="tablaObs">
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>PK</th>
                        <th>Código</th>
                        <th>Regla</th>
                        <th>Acción sugerida</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tablaFilas as $row): ?>
                    <tr data-regla="<?= htmlspecialchars($row['reglaCodigo']) ?>"
                        data-search="<?= htmlspecialchars($row['search']) ?>">
                        <td class="td-num"><?= $row['fila'] ?></td>
                        <td class="td-pk"><?= htmlspecialchars($row['pk']) ?></td>
                        <td class="td-cod"><?= htmlspecialchars($row['codigo']) ?></td>
                        <td>
                            <span class="badge" style="background:#<?= htmlspecialchars($row['color']) ?>">
                                <?= htmlspecialchars($row['reglaNombre']) ?>
                            </span>
                        </td>
                        <td class="td-accion"><?= htmlspecialchars($row['accion']) ?></td>
                        <td class="td-motivo"><?= htmlspecialchars($row['motivo']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php endif; ?>

</main>

<script src="assets/app.js"></script>
</body>
</html>
