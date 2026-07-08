<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Validador\GestorSesiones;

$cfg = require __DIR__ . '/src/config.php';

$id = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['id'] ?? ''));
if (strlen($id) !== 32) {
    header('Location: auditorias.php');
    exit;
}

$gestor = new GestorSesiones($cfg['storage_dir']);
try {
    $meta = $gestor->cargar($id);
} catch (\Throwable) {
    header('Location: auditorias.php');
    exit;
}

$archivoEsc = htmlspecialchars($meta['archivo'] ?? 'Sesión', ENT_QUOTES, 'UTF-8');
$idJson     = json_encode($id, JSON_UNESCAPED_UNICODE);

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $archivoEsc ?> — Revisión CPMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="assets/theme.js"></script>
<link rel="stylesheet" href="assets/estilos.css">
<style>
/* ── Tokens del mockup ────────────────────────────────────────────────── */
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
html,body{height:100%;overflow:hidden;margin:0;background:var(--bg)}
body{font-family:"IBM Plex Sans",system-ui,sans-serif;color:var(--ink);font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:unset;display:flex;flex-direction:column}

/* ── Topbar ───────────────────────────────────────────────────────────── */
.topbar{position:sticky;top:0;z-index:20;background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:18px;padding:0 22px;height:60px;flex-shrink:0}
.brand{display:flex;align-items:center;gap:11px;padding-right:18px;border-right:1px solid var(--line);text-decoration:none;color:inherit}
.brand .glyph{width:30px;height:30px;border-radius:7px;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:600;font-size:15px;font-family:var(--mono);flex-shrink:0}
.brand b{font-weight:600;font-size:14px;display:block}
.brand span{display:block;font-size:11.5px;color:var(--faint)}
.tb-field{display:flex;flex-direction:column;gap:2px}
.tb-field label{font-size:10.5px;text-transform:uppercase;letter-spacing:.6px;color:var(--faint)}
.tb-select{font-family:inherit;font-size:13px;color:var(--ink);background:var(--surface);border:1px solid var(--line-strong);border-radius:var(--radius);padding:5px 24px 5px 10px;appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235b6672' stroke-width='2'><path d='M6 9l6 6 6-6'/></svg>");background-repeat:no-repeat;background-position:right 7px center}
.spacer{flex:1}
.tb-prog{display:flex;align-items:center;gap:10px;min-width:190px}
.tb-prog .meta{font-size:12px;color:var(--muted);white-space:nowrap}
.tb-bar{flex:1;height:6px;border-radius:99px;background:var(--surface-2);border:1px solid var(--line);overflow:hidden}
.tb-bar-fill{display:block;height:100%;background:var(--accent);width:0%;transition:width .3s;border-radius:99px}
.btn-dl{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;border-radius:var(--radius);padding:8px 14px;border:none;background:var(--accent);color:#fff;text-decoration:none;cursor:pointer;white-space:nowrap}
.btn-dl:hover{background:var(--accent-ink)}
.btn-dl svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}
.btn-revalidar{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;border-radius:var(--radius);padding:8px 14px;border:1px solid var(--line-strong);background:var(--surface);color:var(--muted);cursor:pointer;white-space:nowrap;font-family:inherit}
.btn-revalidar:hover{background:var(--surface-2);color:var(--ink)}
.btn-revalidar:disabled{opacity:.5;cursor:not-allowed}
.btn-revalidar svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}
.theme-btn-ix { background: transparent; border: 1px solid var(--line-strong); color: var(--muted); border-radius: 99px; width: 34px; height: 34px; display: grid; place-items: center; cursor: pointer; transition: background .15s, color .15s; margin-left: 12px; }
.theme-btn-ix:hover { background: var(--surface-2); color: var(--ink); }

/* ── Shell ────────────────────────────────────────────────────────────── */
.shell{display:grid;grid-template-columns:300px 1fr;flex:1;overflow:hidden}

/* ── Rail ─────────────────────────────────────────────────────────────── */
.rail{border-right:1px solid var(--line);background:var(--surface);overflow-y:auto;display:flex;flex-direction:column}
.rail-head{position:sticky;top:0;background:var(--surface);padding:14px 16px 10px;border-bottom:1px solid var(--line);z-index:1;flex-shrink:0}
.rail-head h2{font-size:12px;text-transform:uppercase;letter-spacing:.7px;color:var(--faint);font-weight:600;margin:0}
.rail-search{width:100%;font-family:inherit;font-size:13px;padding:7px 10px;border:1px solid var(--line-strong);border-radius:6px;margin:10px 0 8px;background:var(--surface);color:var(--ink)}
.rail-search:focus{outline:none;border-color:var(--accent)}
.seg{display:flex;gap:4px;margin-bottom:6px}
.seg button{flex:1;font-size:12px;padding:5px;border:1px solid var(--line);background:var(--surface);border-radius:6px;color:var(--muted);cursor:pointer;font-family:inherit;transition:background .1s,color .1s}
.seg button.on{background:var(--ink);color:var(--surface);border-color:var(--ink)}
.rail-count{font-size:12px;color:var(--muted);margin-top:4px}
.plist{padding:8px;flex:1}
.pitem{display:flex;gap:10px;align-items:flex-start;padding:10px 11px;border-radius:var(--radius);cursor:pointer;border:1px solid transparent;transition:background .1s}
.pitem:hover{background:var(--surface-2)}
.pitem.on{background:var(--accent-bg);border-color:#cfe6da}
.pitem .dot svg{width:17px;height:17px;stroke-width:2;fill:none;display:block;margin-top:2px}
.pitem .pk{font-family:var(--mono);font-size:12.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--ink)}
.pitem .sub{font-size:11.5px;color:var(--muted);margin-top:2px;display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.ip-chip{font-size:10.5px;padding:1px 6px;border-radius:99px;background:var(--surface-2);border:1px solid var(--line);color:var(--muted)}
.obs-n{font-size:11px;color:var(--accent-ink);font-weight:600}
.plist-empty{padding:1.5rem .75rem;text-align:center;color:var(--muted);font-size:12.5px}

/* ── Detail ───────────────────────────────────────────────────────────── */
.detail{overflow-y:auto;padding:18px 22px 40px;background:var(--bg)}
.det-empty{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:var(--muted);font-size:.9rem}
.det-empty-ico{display:flex;justify-content:center;color:var(--faint)}.det-empty-ico svg{width:40px;height:40px;stroke-width:1.5;fill:none;stroke:currentColor}

/* Gen card */
.gen{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:14px 18px;margin-bottom:14px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start}
.gen .pk{font-family:var(--mono);font-size:16px;font-weight:500;color:var(--ink)}
.gen .ctx{font-size:13px;color:var(--muted);margin-top:5px;display:flex;flex-wrap:wrap;gap:7px;align-items:center}
.pill{font-size:11.5px;background:var(--surface-2);border:1px solid var(--line);border-radius:99px;padding:2px 9px;color:var(--ink)}
.pill-fecha{font-family:var(--mono);font-size:11px;color:var(--muted)}
.badge-dias{font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;background:var(--badge-dias-bg);border:1px solid var(--badge-dias-br);color:var(--badge-dias-tx);font-family:var(--mono);white-space:nowrap}
.dx{margin-top:8px;display:flex;flex-wrap:wrap;gap:6px}
.dx .pill b{font-family:var(--mono);font-weight:500;margin-right:4px}

/* Botón validar */
.btn-validar{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;border-radius:var(--radius);padding:8px 14px;cursor:pointer;white-space:nowrap;font-family:inherit;transition:background .1s,color .1s;flex-shrink:0}
.btn-validar.pendiente{border:1px solid var(--line-strong);background:var(--surface);color:var(--muted)}
.btn-validar.pendiente:hover{background:var(--surface-2)}
.btn-validar.validada{background:var(--accent);border:1px solid var(--accent);color:#fff}
.btn-validar.validada:hover{background:var(--accent-ink)}
.btn-validar:disabled{opacity:.5;cursor:not-allowed}
.btn-validar svg{width:15px;height:15px;stroke-width:2.5;fill:none;stroke:currentColor}

/* Board */
.board{display:grid;grid-template-columns:1.25fr 1fr;gap:14px;align-items:start}
.col{background:var(--surface);border:1px solid var(--line);border-radius:12px;overflow:hidden}
.col-head{display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--line);background:var(--surface-2)}
.col-head h4{font-size:13px;font-weight:600;margin:0}
.col-head svg{flex-shrink:0}
.col-n{margin-left:auto;font-size:12px;color:var(--muted);font-weight:500;white-space:nowrap}
.col-body{padding:8px}
.col-empty{font-size:12.5px;color:var(--faint);text-align:center;padding:18px 8px}

/* ── Grupos ───────────────────────────────────────────────────────────── */
.grp{margin-bottom:8px;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
.grp-head{display:flex;align-items:center;gap:9px;padding:7px 10px;cursor:pointer;background:var(--surface);user-select:none}
.grp-head:hover{background:var(--surface-2)}
.chev{transition:transform .15s;color:var(--faint);display:inline-flex;flex-shrink:0}
.grp-head.collapsed .chev{transform:rotate(-90deg)}
.grp-dot{width:9px;height:9px;border-radius:2px;flex-shrink:0}
.grp-name{font-size:12.5px;font-weight:600}
.grp-count{font-size:11.5px;color:var(--muted)}
.grp-bulk{margin-left:auto;font-size:11px;color:var(--accent-ink);background:none;border:none;padding:3px 6px;border-radius:5px;cursor:pointer;font-family:inherit;opacity:.85}
.grp-bulk:hover{background:var(--accent-bg)}
.grp-body{border-top:1px solid var(--line)}

/* ── Fila compacta ────────────────────────────────────────────────────── */
.crow{display:flex;align-items:center;gap:9px;padding:6px 10px;border-bottom:1px solid var(--line);cursor:pointer}
.crow:last-child{border-bottom:none}
.crow:hover{background:var(--surface-2)}
.crow.rev{opacity:.5}
.chk{flex-shrink:0;width:16px;height:16px;border:1.5px solid var(--line-strong);border-radius:5px;display:grid;place-items:center;background:var(--surface);cursor:pointer;transition:background .1s,border-color .1s}
.chk svg{width:11px;height:11px;stroke:#fff;stroke-width:3;fill:none;opacity:0}
.crow.rev .chk{background:var(--accent);border-color:var(--accent)}
.crow.rev .chk svg{opacity:1}
.cfila{font-family:var(--mono);font-size:11.5px;color:var(--faint);flex-shrink:0;width:42px}
.ccod{font-family:var(--mono);font-size:12.5px;font-weight:500;flex-shrink:0;color:var(--ink)}
.cdesc{font-size:12.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:40px}
.chip{flex-shrink:0;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;white-space:nowrap}
.chip .qty{font-size:12.5px;font-weight:700;margin-left:1px}
.cval{flex-shrink:0;font-family:var(--mono);font-size:11px;color:var(--faint);width:74px;text-align:right}
.acts{flex-shrink:0;display:flex;gap:1px;opacity:0;transition:opacity .12s}
.crow:hover .acts,.crow:focus-within .acts{opacity:1}
.icobtn{border:none;background:none;color:var(--faint);padding:4px;border-radius:6px;line-height:0;cursor:pointer}
.icobtn:hover{background:var(--surface-2);color:var(--ink)}
.icobtn.danger:hover{background:var(--tipo-bg);color:var(--tipo-tx)}
.icobtn svg{width:14px;height:14px;stroke-width:2;fill:none;stroke:currentColor}
.cmot{padding:4px 10px 9px 78px;font-size:12px;color:var(--muted);background:var(--surface-2);border-bottom:1px solid var(--line);line-height:1.4}
.manual-tag{display:inline-block;font-size:.61rem;padding:.01rem .26rem;border-radius:3px;background:var(--man-tag-bg);color:var(--man-tag-tx);border:1px solid var(--man-tag-br);margin-left:.22rem;vertical-align:middle}

/* ── Sin observación ──────────────────────────────────────────────────── */
.sitem{display:flex;align-items:center;gap:9px;padding:7px 10px;border-bottom:1px solid var(--line)}
.sitem:last-child{border-bottom:none}
.sitem:hover{background:var(--surface-2)}
.sacts{margin-left:auto;display:flex;gap:5px;opacity:.45;transition:opacity .12s}
.sitem:hover .sacts{opacity:1}
.sbtn{font-size:11.5px;border:1px solid var(--line-strong);background:var(--surface);border-radius:6px;padding:4px 9px;color:var(--muted);display:inline-flex;align-items:center;gap:4px;white-space:nowrap;cursor:pointer;font-family:inherit}
.sbtn:hover{background:var(--surface-2);color:var(--ink)}
.sbtn.danger:hover{color:var(--tipo-tx);border-color:#eccfcf;background:var(--tipo-bg)}
.sbtn svg{width:13px;height:13px;stroke-width:2;fill:none;stroke:currentColor}

/* ── Dialog ───────────────────────────────────────────────────────────── */
dialog{border:none;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.22);padding:0;width:440px;max-width:95vw;background:var(--surface);color:var(--ink)}
dialog::backdrop{background:rgba(0,0,0,.38)}
.dlg-head{padding:.85rem 1.1rem;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
.dlg-head h3{font-size:.92rem;margin:0}
.dlg-close{border:none;background:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:.2rem .4rem;border-radius:4px;line-height:1}
.dlg-close:hover{background:var(--surface-2)}
.dlg-body{padding:1.1rem;display:flex;flex-direction:column;gap:.65rem}
.dlg-foot{padding:.65rem 1.1rem;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:.45rem}
.field-lbl{display:block;font-size:.76rem;font-weight:600;color:var(--muted);margin-bottom:.2rem}
.field-sel,.field-ta{width:100%;padding:.45rem .7rem;border:1px solid var(--line-strong);border-radius:var(--radius);font-size:.84rem;font-family:inherit;color:var(--ink);background:var(--surface-2)}
.field-sel:focus,.field-ta:focus{outline:none;border-color:var(--accent)}
.field-ta{resize:vertical;min-height:65px}
.btn-cancel{display:inline-flex;align-items:center;font-size:13px;padding:7px 14px;border-radius:var(--radius);border:1px solid var(--line-strong);background:var(--surface);color:var(--muted);cursor:pointer;font-family:inherit}
.btn-cancel:hover{background:var(--surface-2)}
.btn-save{display:inline-flex;align-items:center;font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius);border:none;background:var(--accent);color:#fff;cursor:pointer;font-family:inherit}
.btn-save:hover{background:var(--accent-ink)}
.btn-save:disabled{opacity:.5;cursor:not-allowed}

/* ── Spinner / Toasts ─────────────────────────────────────────────────── */
.spin{display:inline-block;width:18px;height:18px;border:2px solid var(--line);border-top-color:var(--accent);border-radius:50%;animation:spin .65s linear infinite;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
.toast-wrap{position:fixed;bottom:1.25rem;right:1.25rem;display:flex;flex-direction:column;gap:.4rem;z-index:9999}
.toast{padding:.55rem .9rem;border-radius:10px;font-size:.82rem;background:var(--ink);color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.22);animation:slideIn .18s ease}
.toast.ok{background:var(--toast-ok)}.toast.err{background:var(--toast-err)}
@keyframes slideIn{from{transform:translateX(40px);opacity:0}}
[hidden]{display:none !important}
</style>
</head>
<body>

<!-- ── Topbar ──────────────────────────────────────────────────────────── -->
<header class="topbar">
    <a href="auditorias.php" class="brand" title="← Volver a Auditorías">
        <div class="glyph">V</div>
        <div>
            <b>Validador de atenciones</b>
            <span>DIRSAPOL · control de calidad CPMS</span>
        </div>
    </a>
    <div class="tb-field">
        <label for="sbIpress">IPRESS</label>
        <select class="tb-select" id="sbIpress">
            <option value="">Todas</option>
        </select>
    </div>
    <div class="spacer"></div>
    <button class="theme-btn-ix" id="themeToggle" type="button"></button>
    <div class="tb-prog">
        <span class="meta" id="sbProgTxt">–</span>
        <div class="tb-bar">
            <span class="tb-bar-fill" id="sbProgBar"></span>
        </div>
    </div>
    <button class="btn-revalidar" id="btnRevalidar" onclick="revalidarSesion()">
        <svg viewBox="0 0 24 24"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
        Re-validar
    </button>
    <a href="descargar.php?id=<?= htmlspecialchars($id) ?>" class="btn-dl">
        <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
        Descargar Excel
    </a>
</header>

<div class="shell">

    <!-- ── Rail ───────────────────────────────────────────────────────────── -->
    <aside class="rail">
        <div class="rail-head">
            <h2>Prestaciones</h2>
            <input type="search" class="rail-search" id="sbSearch" placeholder="Buscar PK o IPRESS…" autocomplete="off">
            <div class="seg">
                <button class="on" data-estado="">Todas</button>
                <button data-estado="pendiente">Pendientes</button>
                <button data-estado="validada">Validadas</button>
            </div>
            <div class="rail-count" id="railCount"></div>
        </div>
        <div class="plist" id="listaPres"></div>
    </aside>

    <!-- ── Detail ─────────────────────────────────────────────────────────── -->
    <main class="detail" id="detPanel">
        <div class="det-empty" id="detEmpty">
            <span class="det-empty-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
            </span>
            <strong>Selecciona una prestación</strong>
            <span>del listado de la izquierda</span>
        </div>
        <div id="detContent" hidden></div>
    </main>

</div>

<!-- ── Dialog observación ──────────────────────────────────────────────── -->
<dialog id="dlgObs">
    <div class="dlg-head">
        <h3 id="dlgTitulo">Observación</h3>
        <button class="dlg-close" onclick="document.getElementById('dlgObs').close()">✕</button>
    </div>
    <div class="dlg-body">
        <div>
            <label class="field-lbl" for="dlgAccion">Acción</label>
            <select class="field-sel" id="dlgAccion">
                <option value="ELIMINAR">ELIMINAR</option>
                <option value="REVISAR">REVISAR</option>
                <option value="AGREGAR">AGREGAR</option>
                <option value="CAMBIAR POR">CAMBIAR POR</option>
            </select>
        </div>
        <div id="dlgCantidadWrap" style="display:none">
            <label class="field-lbl" for="dlgCantidad">Cantidad</label>
            <input type="number" class="field-sel" id="dlgCantidad" min="1" value="1">
        </div>
        <div>
            <label class="field-lbl" for="dlgMotivo">Motivo</label>
            <textarea class="field-ta" id="dlgMotivo" placeholder="Describe el motivo…"></textarea>
        </div>
        <div id="dlgCambioWrap" style="display:none">
            <label class="field-lbl" for="dlgCambioCodigo">Cambiar por (nuevo código CPMS)</label>
            <input type="text" class="field-sel" id="dlgCambioCodigo" placeholder="Escribe el código nuevo">
        </div>
    </div>
    <div class="dlg-foot">
        <button class="btn-cancel" onclick="document.getElementById('dlgObs').close()">Cancelar</button>
        <button class="btn-save" id="dlgGuardar">Guardar</button>
    </div>
</dialog>

<div class="toast-wrap" id="toasts"></div>

<script>
'use strict';

const SESION_ID = <?= $idJson ?>;

let sesionData  = null;
let detalleData = null;
let pkActual    = null;

let fTexto  = '';
let fIpress = '';
let fEstado = '';

const _obs         = {};
const _filaAbierta = {};

// ── Familias ───────────────────────────────────────────────────────────────
const FAM_META = {
    tipo:   { nombre:'No permitido', st:'var(--tipo-st)', bg:'var(--tipo-bg)', tx:'var(--tipo-tx)' },
    dup:    { nombre:'Duplicados',   st:'var(--dup-st)',  bg:'var(--dup-bg)',  tx:'var(--dup-tx)'  },
    hemo:   { nombre:'Hemograma',    st:'var(--hemo-st)', bg:'var(--hemo-bg)', tx:'var(--hemo-tx)' },
    uro:    { nombre:'Urocultivo',   st:'var(--uro-st)',  bg:'var(--uro-bg)',  tx:'var(--uro-tx)'  },
    sug:    { nombre:'Sugerencia',   st:'var(--sug-st)',  bg:'var(--sug-bg)',  tx:'var(--sug-tx)'  },
    manual: { nombre:'Manual',       st:'var(--man-st)',  bg:'var(--man-bg)',  tx:'var(--man-tx)'  },
};
const FAM_ORDER  = ['tipo', 'dup', 'hemo', 'uro', 'sug', 'manual'];
const colapsados = { dup: true };

// ── API ────────────────────────────────────────────────────────────────────
async function apiGet(ruta, params = {}) {
    const q = new URLSearchParams({ ruta, ...params });
    const r = await fetch('api.php?' + q);
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error');
    return d.data;
}

async function apiPost(ruta, body) {
    const r = await fetch('api.php?ruta=' + encodeURIComponent(ruta), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error');
    return d.data;
}

async function apiMethod(method, body) {
    const r = await fetch('api.php?ruta=observacion', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error');
    return d.data;
}

// ── Utilidades ─────────────────────────────────────────────────────────────
function toast(msg, tipo = '') {
    const el = document.createElement('div');
    el.className = 'toast' + (tipo ? ' ' + tipo : '');
    el.textContent = msg;
    document.getElementById('toasts').appendChild(el);
    setTimeout(() => el.remove(), 3200);
}

function h(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function abreviarIpress(nombre) {
    const n = (nombre || '').toUpperCase();
    if (n.includes('AREQUIPA'))  return 'Arequipa';
    if (n.includes('CHICLAYO'))  return 'Chiclayo';
    if (n.includes('LEGU'))      return 'A. B. LEGUÍA';
    if (n.includes('SAN JOS'))   return 'HG SAN JOSÉ';
    return nombre.length > 20 ? nombre.slice(0, 18) + '…' : nombre;
}

function formatRangoFechas(inicio, fin) {
    if (!inicio && !fin) return '';
    if (!inicio) return fin;
    if (!fin)    return inicio;
    const pi = inicio.split('/');
    const pf = fin.split('/');
    if (pi.length === 3 && pf.length === 3 && pi[2] === pf[2]) {
        return `${pi[0]}/${pi[1]} → ${fin}`;
    }
    return `${inicio} → ${fin}`;
}

function calcularDias(inicio, fin) {
    if (!inicio || !fin) return null;
    const [d1, m1, y1] = inicio.split('/').map(Number);
    const [d2, m2, y2] = fin.split('/').map(Number);
    if (!y1 || !y2) return null;
    const diff = Math.round((new Date(y2, m2 - 1, d2) - new Date(y1, m1 - 1, d1)) / 86400000) + 1;
    return diff > 0 ? diff : null;
}

function familiaDeRegla(regla) {
    if (!regla) return 'manual';
    if (regla.startsWith('PROHIBIDO')) return 'tipo';
    if (regla === 'DUPLICADO')  return 'dup';
    if (regla === 'HEMOGRAMA')  return 'hemo';
    if (regla === 'UROCULTIVO') return 'uro';
    if (regla === 'SUGERENCIA') return 'sug';
    return 'manual';
}

function formatAccion(accion, fam) {
    if (fam === 'dup') {
        const m = (accion || '').match(/cantidad\s*=\s*(\d+)/);
        if (m) return `AGREGAR <span class="qty">×${h(m[1])}</span>`;
    }
    return h(accion || '');
}

// ── Init ───────────────────────────────────────────────────────────────────
async function init() {
    try {
        sesionData = await apiGet('sesion', { id: SESION_ID });
        poblarIpress();
        renderSidebar();
    } catch (e) {
        toast('Error al cargar sesión: ' + e.message, 'err');
    }
}

function poblarIpress() {
    const sel = document.getElementById('sbIpress');
    (sesionData.ipress || []).forEach(ip => {
        const opt = document.createElement('option');
        opt.value = ip.nombre;
        opt.textContent = ip.nombre;
        sel.appendChild(opt);
    });
}

// ── Sidebar ────────────────────────────────────────────────────────────────
function renderSidebar() {
    if (!sesionData) return;

    const { total, validadas, prestaciones } = sesionData;
    const pct = total > 0 ? Math.round(validadas / total * 100) : 0;
    document.getElementById('sbProgTxt').textContent = `${validadas} / ${total} validadas`;
    document.getElementById('sbProgBar').style.width = `${Math.min(100, pct)}%`;

    const txt = fTexto.toLowerCase();
    const lista = prestaciones.filter(p => {
        if (fEstado === 'pendiente' && p.validada) return false;
        if (fEstado === 'validada'  && !p.validada) return false;
        if (fIpress && p.ipress_nom !== fIpress) return false;
        if (txt && !p.pk.includes(txt) && !p.ipress_nom.toLowerCase().includes(txt)) return false;
        return true;
    });

    document.getElementById('railCount').textContent = `${lista.length} prestaciones`;

    const cont = document.getElementById('listaPres');
    cont.innerHTML = '';

    if (lista.length === 0) {
        cont.innerHTML = '<div class="plist-empty">Sin resultados</div>';
        return;
    }

    lista.forEach(p => {
        const el    = document.createElement('div');
        el.className = 'pitem' + (p.pk === pkActual ? ' on' : '');
        const ipNom  = p.ipress_nom || '';
        const nObs   = p.n_obs;
        const dotSvg = p.validada
            ? `<svg viewBox="0 0 24 24" stroke="var(--accent)"><path d="M20 6 9 17l-5-5"/></svg>`
            : `<svg viewBox="0 0 24 24" stroke="var(--faint)"><circle cx="12" cy="12" r="9"/></svg>`;
        const ipLabel = abreviarIpress(ipNom);
        el.innerHTML = `
            <span class="dot">${dotSvg}</span>
            <div style="min-width:0;flex:1">
                <div class="pk">${h(p.pk)}</div>
                <div class="sub">
                    ${ipNom ? `<span class="ip-chip">${h(ipLabel)}</span>` : ''}
                    <span class="obs-n">${nObs} obs.</span>
                </div>
            </div>`;
        el.addEventListener('click', () => seleccionar(p.pk));
        cont.appendChild(el);
    });
}

// ── Selección ──────────────────────────────────────────────────────────────
async function seleccionar(pk) {
    pkActual = pk;
    Object.keys(_filaAbierta).forEach(k => delete _filaAbierta[k]);
    renderSidebar();

    const empty   = document.getElementById('detEmpty');
    const content = document.getElementById('detContent');
    empty.hidden   = true;
    content.hidden = false;
    content.innerHTML = '<div style="padding:2.5rem;text-align:center"><span class="spin"></span></div>';

    try {
        detalleData = await apiGet('prestacion', { id: SESION_ID, pk });
        renderDetalle();
    } catch (e) {
        content.innerHTML = `<div style="padding:2rem;color:var(--tipo-tx)">Error: ${h(e.message)}</div>`;
    }
}

async function recargarDetalle() {
    if (!pkActual) return;
    try {
        [detalleData, sesionData] = await Promise.all([
            apiGet('prestacion', { id: SESION_ID, pk: pkActual }),
            apiGet('sesion',     { id: SESION_ID }),
        ]);
        renderDetalle();
        renderSidebar();
    } catch (e) {
        toast('Error al recargar: ' + e.message, 'err');
    }
}

// ── Render detalle ─────────────────────────────────────────────────────────
function renderDetalle() {
    Object.keys(_obs).forEach(k => delete _obs[k]);

    const d   = detalleData;
    const con = d.con_observacion;
    const sin = d.sin_observacion;

    // Aplanar observaciones
    const items = [];
    con.forEach(proc => {
        proc.observaciones.forEach(obs => {
            _obs[`${proc.fila}_${obs.idx}`] = obs;
            items.push({
                fila: proc.fila, codigo: proc.codigo, desc: proc.desc,
                cantidad: proc.cantidad, valor: proc.valor,
                idx: obs.idx, regla: obs.regla, accion: obs.accion,
                motivo: obs.motivo, color: obs.color, prioridad: obs.prioridad,
                origen: obs.origen, revisada: obs.revisada ?? false,
            });
        });
    });

    // Agrupar por familia
    const grupos = {};
    items.forEach(item => {
        const fam = familiaDeRegla(item.regla);
        (grupos[fam] = grupos[fam] || []).push(item);
    });

    const totalObs   = items.length;
    const revisadasN = items.filter(i => i.revisada).length;

    const gruposHtml = FAM_ORDER
        .filter(f => grupos[f]?.length)
        .map(f => renderGrupo(f, grupos[f]))
        .join('');

    // Diagnósticos como pills
    const dxHtml = d.diagnosticos
        .filter(dg => dg.codigo)
        .map(dg => `<span class="pill"><b>${h(dg.codigo)}</b> ${h(dg.desc)}</span>`)
        .join('');

    const chkSvg = `<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>`;

    const fechaRango = formatRangoFechas(d.fecha_inicio || '', d.fecha_fin || '');
    const dias = calcularDias(d.fecha_inicio || '', d.fecha_fin || '');
    const diasBadge = dias !== null
        ? `<span class="badge-dias">${dias}&nbsp;día${dias === 1 ? '' : 's'}</span>`
        : '';
    const html = `
        <div class="gen">
            <div>
                <div class="pk">${h(d.pk)}</div>
                <div class="ctx">
                    <span class="pill">${h(d.ipress_nom)}</span>
                    ${fechaRango ? `<span class="pill pill-fecha">${h(fechaRango)}</span>${diasBadge}` : ''}
                </div>
                ${dxHtml ? `<div class="dx">${dxHtml}</div>` : ''}
            </div>
            <button class="btn-validar ${d.validada ? 'validada' : 'pendiente'}" id="btnValidar" onclick="toggleValidar()">
                ${chkSvg}${d.validada ? 'Prestación validada' : 'Marcar como validada'}
            </button>
        </div>
        <div class="board">
            <section class="col">
                <div class="col-head">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <h4>Con observación</h4>
                    <span class="col-n">${revisadasN}/${totalObs} revisadas</span>
                </div>
                <div class="col-body">
                    ${gruposHtml || '<div class="col-empty">Sin procedimientos con observaciones</div>'}
                </div>
            </section>
            <section class="col">
                <div class="col-head">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                    <h4>Sin observación</h4>
                    <span class="col-n">${sin.length} procedimientos</span>
                </div>
                <div class="col-body">
                    ${sin.length === 0
                        ? '<div class="col-empty">Todos los procedimientos tienen observación</div>'
                        : sin.map(renderSinObs).join('')}
                </div>
            </section>
        </div>`;

    document.getElementById('detContent').innerHTML = html;
}

function sortDuplicados(items) {
    const firstFila = {};
    items.forEach(item => {
        if (!(item.codigo in firstFila) || item.fila < firstFila[item.codigo])
            firstFila[item.codigo] = item.fila;
    });
    return [...items].sort((a, b) => {
        const df = firstFila[a.codigo] - firstFila[b.codigo];
        if (df !== 0) return df;
        const aElim = /^ELIMINAR/i.test(a.accion) ? 1 : 0;
        const bElim = /^ELIMINAR/i.test(b.accion) ? 1 : 0;
        if (aElim !== bElim) return aElim - bElim;
        return a.fila - b.fila;
    });
}

function renderGrupo(fam, items) {
    const meta   = FAM_META[fam];
    const col    = !!colapsados[fam];
    const revisN = items.filter(i => i.revisada).length;
    const cntTx  = revisN > 0 ? `(${items.length} · ${revisN} rev.)` : `(${items.length})`;
    const ordered = fam === 'dup' ? sortDuplicados(items) : items;
    const rows   = col ? '' : ordered.map(renderObsRow).join('');

    return `<div class="grp">
        <div class="grp-head ${col ? 'collapsed' : ''}" onclick="toggleGrupo('${fam}')">
            <span class="chev"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg></span>
            <span class="grp-dot" style="background:${meta.st}"></span>
            <span class="grp-name">${meta.nombre}</span>
            <span class="grp-count">${cntTx}</span>
            <button class="grp-bulk" onclick="event.stopPropagation();marcarGrupo('${fam}')">marcar revisado</button>
        </div>
        ${col ? '' : `<div class="grp-body">${rows}</div>`}
    </div>`;
}

function renderObsRow(item) {
    const fam    = familiaDeRegla(item.regla);
    const meta   = FAM_META[fam];
    const key    = `${item.fila}_${item.idx}`;
    const abierta = !!_filaAbierta[key];
    const chipHtml = formatAccion(item.accion, fam);
    const manTag   = item.origen === 'manual' ? '<span class="manual-tag">manual</span>' : '';
    const motHtml  = abierta ? `<div class="cmot">${h(item.motivo)}${manTag}</div>` : '';
    const val      = `${item.cantidad} × S/.${parseFloat(item.valor || 0).toFixed(2)}`;

    return `<div class="crow ${item.revisada ? 'rev' : ''}" onclick="toggleFilaMotivo('${key}')">
        <button class="chk" title="Marcar revisada" onclick="event.stopPropagation();marcarObs(${item.fila},${item.idx})">
            <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
        </button>
        <span class="cfila">F.${item.fila}</span>
        <span class="ccod">${h(item.codigo)}</span>
        <span class="cdesc" title="${h(item.desc)}">${h(item.desc)}</span>
        <span class="chip" style="background:${meta.bg};color:${meta.tx}">${chipHtml}</span>
        <span class="cval">${h(val)}</span>
        <span class="acts">
            <button class="icobtn" title="Editar" onclick="event.stopPropagation();abrirEditar(${item.fila},${item.idx})">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            </button>
            <button class="icobtn danger" title="Eliminar" onclick="event.stopPropagation();borrarObs(${item.fila},${item.idx})">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/></svg>
            </button>
        </span>
    </div>${motHtml}`;
}

function renderSinObs(proc) {
    const val = `${proc.cantidad} × S/.${parseFloat(proc.valor || 0).toFixed(2)}`;
    return `<div class="sitem">
        <span class="cfila">F.${proc.fila}</span>
        <span class="ccod">${h(proc.codigo)}</span>
        <span class="cdesc" title="${h(proc.desc)}">${h(proc.desc)}</span>
        <span class="cval">${h(val)}</span>
        <span class="sacts">
            <button class="sbtn" onclick="abrirAgregar(${proc.fila})">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Obs
            </button>
            <button class="sbtn danger" onclick="doEliminarCpms(${proc.fila},'${h(proc.codigo)}')">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/></svg>Eliminar CPMS
            </button>
        </span>
    </div>`;
}

// ── Validar ────────────────────────────────────────────────────────────────
async function toggleValidar() {
    const btn = document.getElementById('btnValidar');
    if (!btn) return;
    btn.disabled = true;
    try {
        const r = await apiPost('validar', { id: SESION_ID, pk: pkActual });
        detalleData.validada = r.validada;
        sesionData.validadas += r.validada ? 1 : -1;
        const enLista = sesionData.prestaciones.find(p => p.pk === pkActual);
        if (enLista) enLista.validada = r.validada;
        renderDetalle();
        renderSidebar();
        toast(r.validada ? 'Prestación validada' : 'Validación desmarcada', 'ok');
    } catch (e) {
        toast('Error: ' + e.message, 'err');
        btn.disabled = false;
    }
}

// ── Re-validar sesión ──────────────────────────────────────────────────────
async function revalidarSesion() {
    if (!confirm('Se re-ejecutarán todas las reglas automáticas sobre el archivo original.\nLas observaciones manuales y el estado de validación se conservarán.\n\n¿Continuar?')) return;
    const btn = document.getElementById('btnRevalidar');
    btn.disabled = true;
    btn.textContent = 'Re-validando…';
    try {
        const r = await apiPost('revalidar', { id: SESION_ID });
        toast(`Re-validación completa: ${r.observaciones_sistema} obs. de sistema`, 'ok');
        sesionData = await apiGet('sesion', { id: SESION_ID });
        renderSidebar();
        if (pkActual) {
            detalleData = await apiGet('prestacion', { id: SESION_ID, pk: pkActual });
            renderDetalle();
        }
    } catch (e) {
        toast('Error al re-validar: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg> Re-validar';
    }
}

// ── Dialog ─────────────────────────────────────────────────────────────────
let dlgMode = null;
let dlgFila = null;
let dlgIdx  = null;

function abrirAgregar(fila) {
    dlgMode = 'add'; dlgFila = fila; dlgIdx = null;
    document.getElementById('dlgTitulo').textContent = `Nueva observación — Fila ${fila}`;
    document.getElementById('dlgAccion').value = 'ELIMINAR';
    document.getElementById('dlgCantidad').value = '1';
    document.getElementById('dlgCantidadWrap').style.display = 'none';
    document.getElementById('dlgCambioCodigo').value = '';
    document.getElementById('dlgCambioWrap').style.display = 'none';
    document.getElementById('dlgMotivo').value = '';
    document.getElementById('dlgObs').showModal();
}

function abrirEditar(fila, idx) {
    const obs = _obs[`${fila}_${idx}`];
    if (!obs) { toast('Observación no encontrada', 'err'); return; }
    dlgMode = 'edit'; dlgFila = fila; dlgIdx = idx;
    document.getElementById('dlgTitulo').textContent = `Editar observación — Fila ${fila}`;
    const mQty = (obs.accion || '').match(/^AGREGAR\s*[—\-]\s*cantidad\s*=\s*(\d+)/i);
    const mCmb = (obs.accion || '').match(/^CAMBIAR POR\s*(.*)$/i);
    if (mQty) {
        document.getElementById('dlgAccion').value = 'AGREGAR';
        document.getElementById('dlgCantidad').value = mQty[1];
        document.getElementById('dlgCantidadWrap').style.display = '';
        document.getElementById('dlgCambioCodigo').value = '';
        document.getElementById('dlgCambioWrap').style.display = 'none';
    } else if (mCmb) {
        document.getElementById('dlgAccion').value = 'CAMBIAR POR';
        document.getElementById('dlgCambioCodigo').value = mCmb[1].trim();
        document.getElementById('dlgCambioWrap').style.display = '';
        document.getElementById('dlgCantidad').value = '1';
        document.getElementById('dlgCantidadWrap').style.display = 'none';
    } else {
        document.getElementById('dlgAccion').value = obs.accion || 'ELIMINAR';
        document.getElementById('dlgCantidad').value = '1';
        document.getElementById('dlgCantidadWrap').style.display = 'none';
        document.getElementById('dlgCambioCodigo').value = '';
        document.getElementById('dlgCambioWrap').style.display = 'none';
    }
    document.getElementById('dlgMotivo').value = obs.motivo || '';
    document.getElementById('dlgObs').showModal();
}

document.getElementById('dlgAccion').addEventListener('change', () => {
    const v = document.getElementById('dlgAccion').value;
    document.getElementById('dlgCantidadWrap').style.display = v === 'AGREGAR'     ? '' : 'none';
    document.getElementById('dlgCambioWrap').style.display   = v === 'CAMBIAR POR' ? '' : 'none';
});

document.getElementById('dlgGuardar').addEventListener('click', async () => {
    let accion = document.getElementById('dlgAccion').value.trim();
    if (accion === 'AGREGAR') {
        const n = parseInt(document.getElementById('dlgCantidad').value, 10) || 1;
        accion = `AGREGAR — cantidad = ${n}`;
    } else if (accion === 'CAMBIAR POR') {
        const cod = document.getElementById('dlgCambioCodigo').value.trim();
        accion = cod ? `CAMBIAR POR ${cod}` : 'CAMBIAR POR';
    }
    const motivo = document.getElementById('dlgMotivo').value.trim();
    if (!motivo) { toast('El motivo es obligatorio', 'err'); return; }
    const btn = document.getElementById('dlgGuardar');
    btn.disabled = true;
    try {
        if (dlgMode === 'add') {
            await apiPost('observacion', { id: SESION_ID, pk: pkActual, fila: dlgFila, accion, motivo });
            toast('Observación agregada', 'ok');
        } else {
            await apiMethod('PUT', { id: SESION_ID, pk: pkActual, fila: dlgFila, idx: dlgIdx, accion, motivo });
            toast('Observación actualizada', 'ok');
        }
        document.getElementById('dlgObs').close();
        await recargarDetalle();
    } catch (e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
    }
});

// ── Toggle motivo / grupo ──────────────────────────────────────────────────
function toggleFilaMotivo(key) {
    _filaAbierta[key] = !_filaAbierta[key];
    renderDetalle();
}

function toggleGrupo(fam) {
    colapsados[fam] = !colapsados[fam];
    renderDetalle();
}

// ── Marcar obs ─────────────────────────────────────────────────────────────
async function marcarObs(fila, idx) {
    const proc = detalleData.con_observacion.find(p => p.fila === fila);
    if (!proc) return;
    const obs = proc.observaciones.find(o => o.idx === idx);
    if (!obs) return;
    obs.revisada = !obs.revisada;
    renderDetalle();
    try {
        await apiPost('revisar-obs', { id: SESION_ID, pk: pkActual, fila, idx });
    } catch (e) {
        obs.revisada = !obs.revisada;
        renderDetalle();
        toast('Error al marcar: ' + e.message, 'err');
    }
}

// ── Marcar grupo ───────────────────────────────────────────────────────────
async function marcarGrupo(fam) {
    const targets = [];
    detalleData.con_observacion.forEach(proc => {
        proc.observaciones.forEach(obs => {
            if (familiaDeRegla(obs.regla) === fam) targets.push(obs);
        });
    });
    if (!targets.length) return;
    const target = !targets.every(o => o.revisada);
    targets.forEach(o => { o.revisada = target; });
    renderDetalle();
    try {
        await apiPost('revisar-grupo', { id: SESION_ID, pk: pkActual, grupo: fam });
        toast(target ? 'Grupo marcado como revisado' : 'Grupo desmarcado', 'ok');
    } catch (e) {
        targets.forEach(o => { o.revisada = !target; });
        renderDetalle();
        toast('Error: ' + e.message, 'err');
    }
}

// ── Borrar obs ─────────────────────────────────────────────────────────────
async function borrarObs(fila, idx) {
    if (!confirm('¿Eliminar esta observación?')) return;
    try {
        await apiMethod('DELETE', { id: SESION_ID, pk: pkActual, fila, idx });
        toast('Observación eliminada', 'ok');
        await recargarDetalle();
    } catch (e) {
        toast('Error: ' + e.message, 'err');
    }
}

// ── Eliminar CPMS ──────────────────────────────────────────────────────────
async function doEliminarCpms(fila, codigo) {
    if (!confirm(`¿Marcar código ${codigo} (fila ${fila}) para eliminación?`)) return;
    try {
        await apiPost('eliminar-cpms', { id: SESION_ID, pk: pkActual, fila, codigo });
        toast(`Código ${codigo} marcado para eliminar`, 'ok');
        await recargarDetalle();
    } catch (e) {
        toast('Error: ' + e.message, 'err');
    }
}

// ── Filtros ────────────────────────────────────────────────────────────────
document.getElementById('sbSearch').addEventListener('input', e => {
    fTexto = e.target.value.trim();
    renderSidebar();
});

document.getElementById('sbIpress').addEventListener('change', e => {
    fIpress = e.target.value;
    renderSidebar();
});

document.querySelectorAll('.seg button').forEach(btn => {
    btn.addEventListener('click', () => {
        fEstado = btn.dataset.estado;
        document.querySelectorAll('.seg button').forEach(b =>
            b.classList.toggle('on', b === btn));
        renderSidebar();
    });
});

// ── Boot ───────────────────────────────────────────────────────────────────
init();
</script>
</body>
</html>
