<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Validador\GestorSesiones;
use Validador\Logger;

$cfg = require __DIR__ . '/src/config.php';

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

$gestor   = new GestorSesiones($cfg['storage_dir']);
$error    = null;
$sesiones = [];

try {
    $sesiones = $gestor->listar();
} catch (\Throwable $e) {
    $error = $e->getMessage();
    Logger::error('Error listando sesiones', $e);
}

function ipressCorta(string $nombre): string
{
    $n = mb_strtoupper($nombre, 'UTF-8');
    if (str_contains($n, 'AREQUIPA')) return 'Arequipa';
    if (str_contains($n, 'CHICLAYO')) return 'Chiclayo';
    if (str_contains($n, 'LEGUIA') || str_contains($n, 'LEGUÍA')) return 'ABL';
    if (str_contains($n, 'GERIATRICO') || str_contains($n, 'GERIÁTRICO') || str_contains($n, 'SAN JOSE') || str_contains($n, 'SAN JOSÉ')) return 'Geriátrico';
    return $nombre;
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditorías — Validador CPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="assets/theme.js"></script>
    <style>
/* ── Tokens del mockup (idénticos a revisar.php) ─────────────────────────── */
:root {
    --ink:#1b2430; --muted:#5b6672; --faint:#8a939e;
    --bg:#eef1f4; --surface:#fff; --surface-2:#f6f8fa;
    --line:#e0e5ea; --line-strong:#cdd4db;
    --accent:#1f7a52; --accent-ink:#19684a; --accent-bg:#e7f2ec;
    --tipo-st:#d64545; --tipo-bg:#fcebeb; --tipo-tx:#791f1f;
    --dup-st:#6d5cc4;  --dup-bg:#efedfb;  --dup-tx:#3c3489;
    --hemo-st:#c98a1a; --hemo-bg:#faeeda; --hemo-tx:#633806;
    --uro-st:#1d9e75;  --uro-bg:#e1f5ee;  --uro-tx:#085041;
    --sug-st:#2f7ed8;  --sug-bg:#e6f1fb;  --sug-tx:#0c447c;
    --man-st:#6b7280;  --man-bg:#eef0f2;  --man-tx:#3f4651;
    --badge-dias-bg: #e0f2f1; --badge-dias-br: #4db6ac; --badge-dias-tx: #00695c;
    --man-tag-bg: #fef9c3; --man-tag-br: #fde047; --man-tag-tx: #854d0e;
    --toast-ok: #166534; --toast-err: #b91c1c;
    --mono:"IBM Plex Mono",ui-monospace,monospace;
    --radius:8px;
}
:root[data-theme="dark"] {
    --ink:#f8fafc; --muted:#94a3b8; --faint:#64748b;
    --bg:#020617; --surface:#0f172a; --surface-2:#1e293b;
    --line:#334155; --line-strong:#475569;
    --accent:#16a34a; --accent-ink:#22c55e; --accent-bg:#064e3b;
    --tipo-st:#fca5a5; --tipo-bg:#450a0a; --tipo-tx:#fca5a5;
    --dup-st:#a78bfa;  --dup-bg:#2e1065;  --dup-tx:#a78bfa;
    --hemo-st:#fcd34d; --hemo-bg:#422006; --hemo-tx:#fde68a;
    --uro-st:#6ee7b7;  --uro-bg:#064e3b;  --uro-tx:#a7f3d0;
    --sug-st:#93c5fd;  --sug-bg:#1e3a8a;  --sug-tx:#bfdbfe;
    --man-st:#94a3b8;  --man-bg:#1e293b;  --man-tx:#cbd5e1;
    --badge-dias-bg: #064e3b; --badge-dias-br: #065f46; --badge-dias-tx: #6ee7b7;
    --man-tag-bg: #422006; --man-tag-br: #713f12; --man-tag-tx: #fde68a;
    --toast-ok: #15803d; --toast-err: #7f1d1d;
}
*{box-sizing:border-box}
html{height:100%}
body{margin:0;font-family:"IBM Plex Sans",system-ui,sans-serif;color:var(--ink);font-size:14px;line-height:1.5;background:var(--bg);-webkit-font-smoothing:antialiased;min-height:100vh}

/* ── Topbar (idéntica a revisar.php) ─────────────────────────────────────── */
.topbar{position:sticky;top:0;z-index:20;background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:18px;padding:0 22px;height:60px;flex-shrink:0}
.brand{display:flex;align-items:center;gap:11px;padding-right:18px;border-right:1px solid var(--line);text-decoration:none;color:inherit}
.brand .glyph{width:30px;height:30px;border-radius:7px;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:600;font-size:15px;font-family:var(--mono);flex-shrink:0}
.brand b{font-weight:600;font-size:14px;display:block}
.brand span{display:block;font-size:11.5px;color:var(--faint)}
.spacer{flex:1}
.theme-btn-ix { background: transparent; border: 1px solid var(--line-strong); color: var(--muted); border-radius: 99px; width: 34px; height: 34px; display: grid; place-items: center; cursor: pointer; transition: background .15s, color .15s; margin-left: 12px; }
.theme-btn-ix:hover { background: var(--surface-2); color: var(--ink); }

/* ── Página ───────────────────────────────────────────────────────────────── */
.page{max-width:960px;margin:0 auto;padding:28px 20px 52px}
.heading h1{font-size:18px;font-weight:600;color:var(--ink)}
.heading p{font-size:13px;color:var(--muted);margin-top:3px}
.toolbar{display:flex;align-items:center;gap:14px;margin:18px 0}
.count{font-size:13px;color:var(--muted)}

.btn-outline{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;border-radius:var(--radius);padding:8px 14px;border:1px solid var(--line-strong);background:var(--surface);color:var(--muted);text-decoration:none;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .1s,color .1s}
.btn-outline:hover{background:var(--surface-2);color:var(--ink)}
.btn-outline svg{width:15px;height:15px;stroke-width:2;fill:none;stroke:currentColor}

/* ── Tarjetas de sesión ───────────────────────────────────────────────────── */
.aud-card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:12px}
.aud-head{display:flex;align-items:flex-start;gap:10px}
.aud-ico{flex-shrink:0;margin-top:2px;line-height:0}
.aud-ico svg{width:18px;height:18px;stroke-width:2;fill:none;stroke:var(--faint)}
.aud-ico.completa svg{stroke:var(--accent)}
.aud-info{flex:1;min-width:0}
.aud-title-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.aud-nombre{font-size:14px;font-weight:600;color:var(--ink);word-break:break-word}
.aud-badge{display:inline-flex;align-items:center;font-size:11px;font-weight:600;padding:2px 9px;border-radius:99px;white-space:nowrap}
.aud-badge.completa{background:var(--accent-bg);color:var(--accent-ink)}
.aud-badge.curso{background:var(--hemo-bg);color:var(--hemo-tx)}
.aud-meta{font-size:12px;color:var(--muted);margin-top:3px}
.aud-meta .id{font-family:var(--mono)}
.aud-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px}
.aud-chip{font-size:11px;padding:2px 8px;border-radius:99px;background:var(--surface-2);border:1px solid var(--line);color:var(--muted)}
.aud-foot{display:flex;align-items:center;gap:14px;margin-top:12px}
.aud-bar-wrap{flex:1;height:6px;border-radius:99px;background:var(--surface-2);border:1px solid var(--line);overflow:hidden}
.aud-bar-fill{display:block;height:100%;background:var(--accent);border-radius:99px;transition:width .3s}
.aud-progress-text{font-size:12px;color:var(--muted);white-space:nowrap;font-family:var(--mono)}
.aud-revisar{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:500;border-radius:var(--radius);padding:7px 12px;border:1px solid var(--line-strong);background:var(--surface);color:var(--muted);text-decoration:none;white-space:nowrap;transition:background .1s,color .1s,border-color .1s}
.aud-revisar:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.aud-revisar svg{width:14px;height:14px;stroke-width:2;fill:none;stroke:currentColor}

/* ── Estado vacío ─────────────────────────────────────────────────────────── */
.empty-card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:48px 20px;text-align:center;color:var(--muted)}
.empty-card .ico{display:flex;justify-content:center;color:var(--faint);margin-bottom:10px}
.empty-card .ico svg{width:36px;height:36px;stroke-width:1.75;fill:none;stroke:currentColor}
.empty-card p{margin:4px 0;font-size:13px}
.empty-card a{color:var(--accent-ink);font-weight:500;text-decoration:none}
.empty-card a:hover{text-decoration:underline}

/* ── Alerta de error ──────────────────────────────────────────────────────── */
.alert-error{background:var(--tipo-bg);border:1px solid var(--tipo-st);color:var(--tipo-tx);border-radius:var(--radius);padding:12px 14px;margin:18px 0;font-size:13px;line-height:1.5}
.alert-error strong{display:block;margin-bottom:2px}
    </style>
</head>
<body>

<header class="topbar">
    <a class="brand" href="index.php" title="Volver al validador">
        <div class="glyph">V</div>
        <div><b>Validador de atenciones</b><span>DIRSAPOL · control de calidad CPMS</span></div>
    </a>
    <div class="spacer"></div>
    <button class="theme-btn-ix" id="themeToggle" type="button" aria-label="Cambiar tema"></button>
</header>

<main class="page">

    <div class="heading">
        <h1>Auditorías</h1>
        <p>Sesiones de auditoría registradas. Cada subida genera una sesión independiente.</p>
    </div>

    <?php if ($error): ?>
    <div class="alert-error">
        <strong>Error al cargar las sesiones.</strong>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <a href="index.php" class="btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            Volver al validador
        </a>
        <span class="count"><?= count($sesiones) ?> sesión<?= count($sesiones) !== 1 ? 'es' : '' ?> registrada<?= count($sesiones) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($sesiones)): ?>
    <div class="empty-card">
        <div class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
        </div>
        <p><strong>No hay sesiones de auditoría todavía.</strong></p>
        <p>Sube un archivo Excel desde el <a href="index.php">validador</a> para crear la primera sesión.</p>
    </div>
    <?php else: ?>

    <?php foreach ($sesiones as $s): ?>
    <?php
        $fechaFormato = 'N/D';
        try {
            $dt = new \DateTimeImmutable($s['creada']);
            $fechaFormato = $dt->format('d/m/Y H:i');
        } catch (\Throwable) {}
        $completa = $s['validadas'] >= $s['total'] && $s['total'] > 0;
        $progreso = min(100, (float) $s['progreso']);
    ?>
    <div class="aud-card">
        <div class="aud-head">
            <div class="aud-ico<?= $completa ? ' completa' : '' ?>">
                <?php if ($completa): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14.5l-3-3"/></svg>
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/></svg>
                <?php endif; ?>
            </div>
            <div class="aud-info">
                <div class="aud-title-row">
                    <span class="aud-nombre"><?= htmlspecialchars($s['archivo']) ?></span>
                    <?php if ($completa): ?>
                    <span class="aud-badge completa">Completa</span>
                    <?php else: ?>
                    <span class="aud-badge curso">En curso</span>
                    <?php endif; ?>
                </div>
                <div class="aud-meta">
                    <?= number_format($s['total']) ?> prestaciones · <?= htmlspecialchars($fechaFormato) ?> ·
                    <span class="id" title="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars(substr($s['id'], 0, 8)) ?>…</span>
                </div>
                <?php if (!empty($s['ipress'])): ?>
                <div class="aud-chips">
                    <?php foreach ($s['ipress'] as $ip): ?>
                    <span class="aud-chip"><?= htmlspecialchars(ipressCorta($ip['nombre'])) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="aud-foot">
            <div class="aud-bar-wrap">
                <span class="aud-bar-fill" style="width:<?= $progreso ?>%"></span>
            </div>
            <span class="aud-progress-text"><?= number_format($s['validadas']) ?> / <?= number_format($s['total']) ?> (<?= number_format($s['progreso'], 1) ?>%)</span>
            <a href="revisar.php?id=<?= htmlspecialchars($s['id']) ?>" class="aud-revisar">
                Revisar
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</main>
</body>
</html>
