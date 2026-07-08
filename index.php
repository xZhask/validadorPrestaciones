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
$resultado        = null;
$sinObservaciones = false;
$totalAtenciones  = 0;
$totalFilasObs    = 0;
$sesionId         = null;

$gestor = new GestorSesiones($cfg['storage_dir']);

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
            $sesionId = $gestor->crear($file['tmp_name'], $file['name']);

            $lector    = new LectorExcel();
            $datos     = $lector->cargar($file['tmp_name']);
            $motor     = construirMotor($cfg);
            $resultado = $motor->validar($datos['atenciones']);

            $totalAtenciones  = count($datos['atenciones']);
            $sinObservaciones = $resultado->vacio();

            $estado  = $gestor->cargar($sesionId);
            $porFila = $resultado->porFila();
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

            if (!$sinObservaciones) {
                $totalFilasObs = count($resultado->resolucionPorFila());
            }

            unset($estado, $porFila, $datos, $resultado);
            gc_collect_cycles();

            Logger::info(sprintf(
                'Validación completada: %s — %d atenciones, %d con observaciones — sesión %s',
                $file['name'],
                $totalAtenciones,
                $totalFilasObs,
                $sesionId,
            ));

        } catch (\Throwable $e) {
            $error    = $e->getMessage();
            $sesionId = null;
            Logger::error('Error procesando archivo: ' . ($_FILES['archivo']['name'] ?? 'desconocido'), $e);
        }
    }
}

$sesionesRecientes = array_slice($gestor->listar(), 0, 3);

// Columnas requeridas (primeras 10) y opcionales (diagnósticos, últimas 4)
$colsRequeridas = array_values(array_slice($cfg['columnas'], 0, 10));
$colsOpcionales = array_values(array_slice($cfg['columnas'], 10));

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validador CPMS — Nueva validación</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="assets/theme.js"></script>
    <style>
/* ── Tokens ───────────────────────────────────────────────────────────────── */
:root {
    --ink:#1b2430; --muted:#5b6672; --faint:#8a939e;
    --bg:#eef1f4; --surface:#fff; --surface-2:#f6f8fa;
    --line:#e0e5ea; --line-strong:#cdd4db;
    --accent:#1f7a52; --accent-ink:#19684a; --accent-bg:#e7f2ec; --accent-line:#cfe6da;
    --err-bg:#fcebeb; --err-line:#e5b0b0; --err-tx:#791f1f;
    --success-bg:#166534; --success-hover:#14532d;
    --mono:"IBM Plex Mono",ui-monospace,monospace;
    --radius:8px;
}
:root[data-theme="dark"] {
    --ink:#f8fafc; --muted:#94a3b8; --faint:#64748b;
    --bg:#020617; --surface:#0f172a; --surface-2:#1e293b;
    --line:#334155; --line-strong:#475569;
    --accent:#16a34a; --accent-ink:#22c55e; --accent-bg:#064e3b; --accent-line:#065f46;
    --err-bg:#450a0a; --err-line:#7f1d1d; --err-tx:#fca5a5;
    --success-bg:#15803d; --success-hover:#16a34a;
    --scroll-bg:#0f172a; --scroll-thumb:#334155; --scroll-thumb-hover:#475569;
}
* {
    box-sizing:border-box;margin:0;padding:0;
    scrollbar-width: thin;
    scrollbar-color: var(--scroll-thumb, #cbd5e1) var(--scroll-bg, transparent);
}
*::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
*::-webkit-scrollbar-track {
    background: var(--scroll-bg, transparent);
}
*::-webkit-scrollbar-thumb {
    background-color: var(--scroll-thumb, #cbd5e1);
    border-radius: 10px;
}
*::-webkit-scrollbar-thumb:hover {
    background-color: var(--scroll-thumb-hover, #94a3b8);
}
html{height:100%}
body{font-family:"IBM Plex Sans",system-ui,sans-serif;color:var(--ink);font-size:14px;line-height:1.5;background:var(--bg);-webkit-font-smoothing:antialiased;min-height:100vh}

/* ── Topbar ───────────────────────────────────────────────────────────────── */
.ix-topbar{background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:18px;padding:0 22px;height:60px;position:sticky;top:0;z-index:10}
.ix-brand{display:flex;align-items:center;gap:11px;text-decoration:none;color:inherit;padding-right:18px;border-right:1px solid var(--line)}
.ix-glyph{width:30px;height:30px;border-radius:7px;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:600;font-size:15px;font-family:var(--mono);flex-shrink:0}
.ix-brand b{font-weight:600;font-size:14px;display:block}
.ix-brand span{display:block;font-size:11.5px;color:var(--faint)}
.ix-spacer{flex:1}
.ix-nav{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:var(--muted);text-decoration:none;padding:6px 10px;border-radius:var(--radius);transition:background .1s,color .1s}
.ix-nav:hover{background:var(--surface-2);color:var(--ink)}
.ix-nav svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor;flex-shrink:0}

/* ── Page / Layout ────────────────────────────────────────────────────────── */
.ix-page{max-width:1200px;margin:0 auto;padding:28px 20px 52px}
.ix-layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start}
@media(max-width:860px){.ix-layout{grid-template-columns:1fr}}

/* ── Heading ──────────────────────────────────────────────────────────────── */
.ix-heading{margin-bottom:16px}
.ix-heading h1{font-size:18px;font-weight:600;color:var(--ink)}
.ix-heading p{font-size:13px;color:var(--muted);margin-top:3px}

/* ── Card ─────────────────────────────────────────────────────────────────── */
.ix-card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:18px;margin-bottom:12px}

/* ── Alert error ──────────────────────────────────────────────────────────── */
.ix-alert-err{background:var(--err-bg);border:1px solid var(--err-line);color:var(--err-tx);border-radius:var(--radius);padding:12px 14px;margin-bottom:12px;font-size:13px;line-height:1.5}
.ix-alert-err strong{display:block;margin-bottom:2px}

/* ── Dropzone ─────────────────────────────────────────────────────────────── */
.ix-dropzone{border:2px dashed var(--line-strong);border-radius:10px;padding:32px 16px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;position:relative;margin-bottom:14px}
.ix-dropzone:hover,.ix-dropzone.drag-over{border-color:var(--accent);background:var(--accent-bg)}
.ix-dropzone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.ix-drop-ico{display:flex;justify-content:center;margin-bottom:10px;color:var(--muted)}
.ix-drop-ico svg{width:36px;height:36px;stroke-width:1.75;fill:none;stroke:currentColor}
.ix-drop-label{font-size:14px;color:var(--muted);margin-bottom:4px}
.ix-drop-label strong{color:var(--ink)}
.ix-drop-sub{font-size:12px;color:var(--faint)}

/* ── File card (archivo seleccionado) ────────────────────────────────────── */
.ix-file-card{display:flex;align-items:center;gap:10px;border:1px solid var(--accent-line);border-radius:10px;background:var(--accent-bg);padding:10px 14px;margin-bottom:14px}
.ix-file-ico{color:var(--accent);flex-shrink:0;line-height:0}
.ix-file-ico svg{width:20px;height:20px;stroke-width:2;fill:none;stroke:currentColor}
.ix-file-info{flex:1;min-width:0}
.ix-file-name{font-size:13px;font-weight:500;color:var(--ink);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ix-file-meta{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-top:2px}
.ix-hoja-chip{font-size:11px;background:var(--surface);border:1px solid var(--accent-line);border-radius:99px;padding:1px 7px;color:var(--accent-ink);font-weight:500}
.ix-file-close{border:none;background:none;color:var(--faint);cursor:pointer;padding:4px;border-radius:6px;line-height:0;flex-shrink:0;transition:background .1s,color .1s}
.ix-file-close:hover{background:rgba(0,0,0,.07);color:var(--ink)}
.ix-file-close svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}

/* ── Form actions ─────────────────────────────────────────────────────────── */
.ix-form-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.ix-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--radius);font-size:13px;font-weight:500;border:none;cursor:pointer;text-decoration:none;font-family:inherit;transition:background .1s,opacity .1s;white-space:nowrap}
.ix-btn:disabled{opacity:.38;cursor:not-allowed;pointer-events:none}
.ix-btn svg{width:15px;height:15px;stroke-width:2;fill:none;stroke:currentColor;flex-shrink:0}
.ix-btn-primary{background:var(--accent);color:#fff}
.ix-btn-primary:not(:disabled):hover{background:var(--accent-ink)}
.ix-btn-success{background:var(--success-bg);color:#fff}
.ix-btn-success:not(:disabled):hover{background:var(--success-hover)}
.ix-btn-outline{background:var(--surface);color:var(--muted);border:1px solid var(--line-strong)}
.ix-btn-outline:hover{background:var(--surface-2);color:var(--ink)}

/* ── Requisitos plegables ─────────────────────────────────────────────────── */
.ix-reqs{margin-bottom:12px}
.ix-reqs-toggle{display:flex;align-items:center;gap:6px;background:none;border:none;cursor:pointer;font-family:inherit;font-size:13px;color:var(--muted);padding:6px 0;width:100%;text-align:left;transition:color .1s}
.ix-reqs-toggle:hover{color:var(--ink)}
.ix-reqs-toggle svg{width:14px;height:14px;stroke-width:2.5;fill:none;stroke:currentColor;transition:transform .15s;flex-shrink:0}
.ix-reqs-toggle.open svg{transform:rotate(90deg)}
.ix-reqs-body{border:1px solid var(--line);border-radius:var(--radius);padding:12px 14px;margin-top:6px;background:var(--surface)}
.ix-reqs-group{margin-bottom:10px}
.ix-reqs-group:last-child{margin-bottom:0}
.ix-reqs-label{font-size:11.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block}
.ix-chips-row{display:flex;flex-wrap:wrap;gap:5px}
.ix-chip-req{font-size:11.5px;font-family:var(--mono);padding:2px 8px;border-radius:5px;border:1px solid var(--accent-line);background:var(--accent-bg);color:var(--accent-ink);white-space:nowrap}
.ix-chip-opt{font-size:11.5px;font-family:var(--mono);padding:2px 8px;border-radius:5px;border:1px solid var(--line);background:var(--surface-2);color:var(--muted);white-space:nowrap}

/* ── Procesando ───────────────────────────────────────────────────────────── */
.ix-processing{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:32px 20px;text-align:center;margin-bottom:12px}
.ix-proc-spin{width:32px;height:32px;border:3px solid var(--line);border-top-color:var(--accent);border-radius:50%;animation:ixspin .65s linear infinite;margin:0 auto 18px}
@keyframes ixspin{to{transform:rotate(360deg)}}
.ix-proc-title{font-size:14px;font-weight:500;color:var(--ink);margin-bottom:12px}
.ix-proc-steps{display:flex;flex-direction:column;gap:4px}
.ix-proc-step{font-size:13px;color:var(--faint);transition:color .2s}
.ix-proc-step.active{color:var(--ink);font-weight:500}
.ix-proc-step.done{color:var(--accent)}

/* ── Estado final (resultado) ─────────────────────────────────────────────── */
.ix-result{background:var(--surface);border:1px solid var(--accent-line);border-radius:12px;padding:20px 18px;margin-bottom:12px}
.ix-result-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
.ix-result-ico{width:42px;height:42px;border-radius:10px;background:var(--accent-bg);display:grid;place-items:center;flex-shrink:0}
.ix-result-ico svg{width:22px;height:22px;stroke-width:2.25;fill:none;stroke:var(--accent)}
.ix-result-title{font-size:15px;font-weight:600;color:var(--ink)}
.ix-result-sub{font-size:13px;color:var(--muted);margin-top:2px}
.ix-result-actions{display:flex;gap:8px;flex-wrap:wrap}

/* ── Panel auditorías recientes ───────────────────────────────────────────── */
.ix-audits-head{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--line)}
.ix-audits-head svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:var(--muted);flex-shrink:0}
.ix-audits-title{font-size:14px;font-weight:600;color:var(--ink);flex:1}
.ix-audits-all{font-size:12px;color:var(--accent-ink);text-decoration:none;font-weight:500}
.ix-audits-all:hover{text-decoration:underline}
.ix-session{border:1px solid var(--line);border-radius:var(--radius);padding:11px 12px;margin-bottom:8px}
.ix-session:last-child{margin-bottom:0}
.ix-session-head{display:flex;align-items:flex-start;gap:8px;margin-bottom:8px}
.ix-ses-ico{flex-shrink:0;margin-top:1px;line-height:0}
.ix-ses-ico svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:var(--muted)}
.ix-ses-ico.done svg{stroke:var(--accent)}
.ix-ses-info{flex:1;min-width:0}
.ix-ses-name{font-size:12.5px;font-weight:500;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.ix-ses-date{font-size:11.5px;color:var(--faint);margin-top:1px;display:block}
.ix-ses-btn{font-size:12px;font-weight:500;padding:4px 10px;border-radius:6px;background:var(--surface-2);border:1px solid var(--line-strong);color:var(--muted);text-decoration:none;white-space:nowrap;flex-shrink:0;transition:background .1s,color .1s}
.ix-ses-btn:hover{background:var(--surface);color:var(--ink)}
.ix-ses-prog{display:flex;align-items:center;gap:8px}
.ix-ses-bar{flex:1;height:5px;border-radius:99px;background:var(--surface-2);border:1px solid var(--line);overflow:hidden}
.ix-ses-bar-fill{height:100%;background:var(--accent);border-radius:99px}
.ix-ses-pct{font-family:var(--mono);font-size:11px;color:var(--muted);white-space:nowrap;min-width:42px;text-align:right}
.ix-no-sessions{text-align:center;padding:22px 10px;color:var(--faint);font-size:13px}
.theme-btn-ix {
    background: transparent;
    border: 1px solid var(--line-strong);
    color: var(--muted);
    border-radius: 99px;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: background .15s, color .15s;
    margin-right: 12px;
}
.theme-btn-ix:hover {
    background: var(--surface-2);
    color: var(--ink);
}
    </style>
</head>
<body>

<!-- ── Topbar ──────────────────────────────────────────────────────────────── -->
<header class="ix-topbar">
    <a href="index.php" class="ix-brand">
        <div class="ix-glyph">V</div>
        <div>
            <b>Validador de atenciones</b>
            <span>DIRSAPOL · control de calidad CPMS</span>
        </div>
    </a>
    <div class="ix-spacer"></div>
    <button class="theme-btn-ix" id="themeToggle" type="button"></button>
    <a href="auditorias.php" class="ix-nav">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7h18M3 12h18M3 17h18"/>
        </svg>
        Ver auditorías
    </a>
</header>

<!-- ── Page ────────────────────────────────────────────────────────────────── -->
<div class="ix-page">
    <div class="ix-layout">

        <!-- ── Columna izquierda: carga / resultado ──────────────────────── -->
        <div>
            <div class="ix-heading">
                <h1>Nueva validación</h1>
                <p>Sube el archivo de atenciones para generar el reporte de observaciones.</p>
            </div>

            <?php if ($error): ?>
            <div class="ix-alert-err">
                <strong>No se pudo procesar el archivo.</strong>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($sesionId): ?>
            <!-- ── Estado final ────────────────────────────────────────── -->
            <div class="ix-result">
                <div class="ix-result-head">
                    <div class="ix-result-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </div>
                    <div>
                        <div class="ix-result-title">
                            <?= $sinObservaciones ? 'Sin observaciones' : 'Archivo procesado' ?>
                        </div>
                        <div class="ix-result-sub">
                            <?= number_format($totalAtenciones) ?> prestaciones<?php if (!$sinObservaciones): ?>
                            &nbsp;·&nbsp; <?= number_format($totalFilasObs) ?> con observaciones<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="ix-result-actions">
                    <a href="revisar.php?id=<?= htmlspecialchars($sesionId) ?>" class="ix-btn ix-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                        Abrir revisión
                    </a>
                    <a href="descargar.php?id=<?= htmlspecialchars($sesionId) ?>" class="ix-btn ix-btn-success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/>
                        </svg>
                        Descargar Excel
                    </a>
                    <a href="?" class="ix-btn ix-btn-outline">Nueva validación</a>
                </div>
            </div>

            <?php else: ?>
            <!-- ── Formulario de carga ─────────────────────────────────── -->
            <div class="ix-card" id="uploadSection">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <!-- Dropzone -->
                    <div class="ix-dropzone" id="dropZone">
                        <div class="ix-drop-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 16V4m0 0l-4 4m4-4l4 4"/>
                                <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>
                            </svg>
                        </div>
                        <p class="ix-drop-label">
                            Arrastra tu <strong>.xlsx</strong> aquí o haz clic para seleccionar
                        </p>
                        <p class="ix-drop-sub">Hoja «<?= htmlspecialchars($cfg['hoja']) ?>» · ~16 000 filas</p>
                        <input type="file" name="archivo" id="archivoInput" accept=".xlsx">
                    </div>

                    <!-- Archivo seleccionado (JS lo muestra) -->
                    <div class="ix-file-card" id="fileCard" hidden>
                        <span class="ix-file-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <path d="M14 2v6h6"/>
                            </svg>
                        </span>
                        <div class="ix-file-info">
                            <span class="ix-file-name" id="fileName"></span>
                            <span class="ix-file-meta">
                                <span id="fileSize"></span>
                                <span class="ix-hoja-chip">Hoja: <?= htmlspecialchars($cfg['hoja']) ?></span>
                            </span>
                        </div>
                        <button type="button" class="ix-file-close" id="btnClearFile" title="Quitar archivo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Acciones -->
                    <div class="ix-form-actions">
                        <button type="submit" class="ix-btn ix-btn-primary" id="btnProcesar" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                            Procesar archivo
                        </button>
                        <a href="?" class="ix-btn ix-btn-outline">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Requisitos plegables (fuera del dropzone) -->
            <div class="ix-reqs" id="reqsWrapper">
                <button class="ix-reqs-toggle" id="reqsToggle" type="button" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 6l6 6-6 6"/>
                    </svg>
                    Requisitos del archivo
                </button>
                <div class="ix-reqs-body" id="reqsBody" hidden>
                    <div class="ix-reqs-group">
                        <span class="ix-reqs-label">Requeridas</span>
                        <div class="ix-chips-row">
                            <?php foreach ($colsRequeridas as $col): ?>
                            <span class="ix-chip-req"><?= htmlspecialchars($col) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($colsOpcionales): ?>
                    <div class="ix-reqs-group">
                        <span class="ix-reqs-label">Opcionales</span>
                        <div class="ix-chips-row">
                            <?php foreach ($colsOpcionales as $col): ?>
                            <span class="ix-chip-opt"><?= htmlspecialchars($col) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Feedback de procesamiento (JS lo muestra al enviar) -->
            <div class="ix-processing" id="processingSection" hidden>
                <div class="ix-proc-spin"></div>
                <div class="ix-proc-title">Procesando archivo…</div>
                <div class="ix-proc-steps">
                    <div class="ix-proc-step" id="pStep0">Leyendo archivo…</div>
                    <div class="ix-proc-step" id="pStep1">Detectando columnas…</div>
                    <div class="ix-proc-step" id="pStep2">Agrupando prestaciones…</div>
                    <div class="ix-proc-step" id="pStep3">Aplicando reglas…</div>
                    <div class="ix-proc-step" id="pStep4">Generando reporte…</div>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <!-- ── Columna derecha: auditorías recientes ──────────────────────── -->
        <aside>
            <div class="ix-card">
                <div class="ix-audits-head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18"/>
                        <path d="M7 14l3-3 3 3 4-5"/>
                    </svg>
                    <span class="ix-audits-title">Auditorías recientes</span>
                    <a href="auditorias.php" class="ix-audits-all">Ver todas</a>
                </div>

                <?php if ($sesionesRecientes): ?>
                <?php foreach ($sesionesRecientes as $s):
                    $completada = ($s['total'] > 0 && $s['validadas'] >= $s['total']);
                    $pct        = (int) min(100, max(0, $s['progreso']));
                    $fechaStr   = date('d/m/Y H:i', (int) strtotime($s['creada']));
                ?>
                <div class="ix-session">
                    <div class="ix-session-head">
                        <span class="ix-ses-ico <?= $completada ? 'done' : '' ?>">
                            <?php if ($completada): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/>
                                <path d="M22 4 12 14.5l-3-3"/>
                            </svg>
                            <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 8v4l3 2"/>
                            </svg>
                            <?php endif; ?>
                        </span>
                        <div class="ix-ses-info">
                            <span class="ix-ses-name" title="<?= htmlspecialchars($s['archivo']) ?>"><?= htmlspecialchars($s['archivo']) ?></span>
                            <span class="ix-ses-date"><?= $fechaStr ?></span>
                        </div>
                        <a href="revisar.php?id=<?= htmlspecialchars($s['id']) ?>" class="ix-ses-btn">
                            <?= $completada ? 'Ver' : 'Continuar' ?>
                        </a>
                    </div>
                    <div class="ix-ses-prog">
                        <div class="ix-ses-bar">
                            <div class="ix-ses-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="ix-ses-pct"><?= (int)$s['validadas'] ?>/<?= (int)$s['total'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="ix-no-sessions">No hay auditorías todavía.</div>
                <?php endif; ?>
            </div>
        </aside>

    </div>
</div>

<script>
'use strict';

// ── Dropzone ──────────────────────────────────────────────────────────────
const dropZone     = document.getElementById('dropZone');
const archivoInput = document.getElementById('archivoInput');
const fileCard     = document.getElementById('fileCard');
const fileNameEl   = document.getElementById('fileName');
const fileSizeEl   = document.getElementById('fileSize');
const btnClearFile = document.getElementById('btnClearFile');
const btnProcesar  = document.getElementById('btnProcesar');
const uploadForm   = document.getElementById('uploadForm');

if (dropZone) {
    ['dragenter', 'dragover'].forEach(ev =>
        dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('drag-over'); }));
    ['dragleave', 'drop'].forEach(ev =>
        dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.remove('drag-over'); }));
    dropZone.addEventListener('drop', e => {
        const f = e.dataTransfer?.files?.[0];
        if (f) setFile(f);
    });
}

if (archivoInput) {
    archivoInput.addEventListener('change', () => {
        if (archivoInput.files.length) setFile(archivoInput.files[0]);
    });
}

if (btnClearFile) {
    btnClearFile.addEventListener('click', clearFile);
}

function setFile(f) {
    fileNameEl.textContent = f.name;
    fileSizeEl.textContent = formatBytes(f.size);
    dropZone.hidden        = true;
    fileCard.hidden        = false;
    btnProcesar.disabled   = false;
}

function clearFile() {
    if (archivoInput) archivoInput.value = '';
    dropZone.hidden      = false;
    fileCard.hidden      = true;
    btnProcesar.disabled = true;
}

function formatBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
    return (n / 1048576).toFixed(1) + ' MB';
}

// ── Requisitos plegables ──────────────────────────────────────────────────
const reqsToggle = document.getElementById('reqsToggle');
const reqsBody   = document.getElementById('reqsBody');

if (reqsToggle && reqsBody) {
    reqsToggle.addEventListener('click', () => {
        const willOpen = reqsBody.hidden;
        reqsBody.hidden = !willOpen;
        reqsToggle.classList.toggle('open', willOpen);
        reqsToggle.setAttribute('aria-expanded', String(willOpen));
    });
}

// ── Feedback de procesamiento ─────────────────────────────────────────────
const processingSection = document.getElementById('processingSection');
const uploadSection     = document.getElementById('uploadSection');
const reqsWrapper       = document.getElementById('reqsWrapper');

if (uploadForm) {
    uploadForm.addEventListener('submit', () => {
        if (uploadSection)    uploadSection.hidden    = true;
        if (reqsWrapper)      reqsWrapper.hidden      = true;
        if (processingSection) processingSection.hidden = false;

        const steps  = ['pStep0','pStep1','pStep2','pStep3','pStep4'];
        const delays = [0, 900, 2000, 3400, 5200];
        steps.forEach((id, i) => {
            setTimeout(() => {
                const el = document.getElementById(id);
                if (!el) return;
                steps.slice(0, i).forEach(prev => {
                    const p = document.getElementById(prev);
                    if (p) { p.classList.remove('active'); p.classList.add('done'); }
                });
                el.classList.add('active');
            }, delays[i]);
        });
    });
}
</script>
</body>
</html>
