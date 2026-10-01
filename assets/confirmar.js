'use strict';

/**
 * Diálogo de confirmación propio, en lugar del confirm() del navegador:
 * botones que dicen lo que hacen y, en acciones destructivas, el foco en
 * "Cancelar" para que un Enter por inercia no borre nada.
 *
 *   if (!await confirmar({
 *       titulo:  'Eliminar auditoría',
 *       mensaje: 'Se eliminará … junto con su Excel original.',
 *       aviso:   'Tiene 12 prestaciones validadas: ese avance se pierde.',
 *       aceptar: 'Eliminar auditoría',
 *       peligro: true,
 *   })) return;
 *
 * Usa los tokens de color de la página (--surface, --ink, --line, …).
 * Escape o la ✕ equivalen a Cancelar.
 */
(function () {
    const CSS = `
dialog.cfm{margin:auto;border:none;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.22);padding:0;width:440px;max-width:95vw;background:var(--surface);color:var(--ink);font-family:inherit}
dialog.cfm::backdrop{background:rgba(0,0,0,.38)}
.cfm-head{padding:.85rem 1.1rem;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:.5rem}
.cfm-head h3{font-size:.92rem;margin:0}
.cfm-x{border:none;background:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:.2rem .4rem;border-radius:4px;line-height:1}
.cfm-x:hover{background:var(--surface-2)}
.cfm-body{padding:1.1rem;display:flex;flex-direction:column;gap:.65rem;font-size:13.5px;line-height:1.5}
.cfm-body p{margin:0;white-space:pre-line}
.cfm-aviso{background:var(--tipo-bg);color:var(--tipo-tx);border:1px solid var(--tipo-st);border-radius:8px;padding:.55rem .75rem;font-weight:500}
.cfm-foot{padding:.65rem 1.1rem;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:.45rem}
.cfm-foot button{display:inline-flex;align-items:center;font-size:13px;padding:7px 14px;border-radius:var(--radius,8px);cursor:pointer;font-family:inherit}
.cfm-no{border:1px solid var(--line-strong);background:var(--surface);color:var(--muted)}
.cfm-no:hover{background:var(--surface-2)}
.cfm-si{border:none;font-weight:500;background:var(--accent);color:#fff}
.cfm-si:hover{background:var(--accent-ink)}
.cfm-si.peligro{background:#c53030}
.cfm-si.peligro:hover{background:#9b2c2c}
.cfm-foot button:focus{outline:2px solid var(--accent);outline-offset:2px}`;  // siempre visible: indica qué hace Enter

    let dlg = null;

    function crear() {
        const st = document.createElement('style');
        st.textContent = CSS;
        document.head.appendChild(st);

        dlg = document.createElement('dialog');
        dlg.className = 'cfm';
        dlg.innerHTML = `
            <form method="dialog">
                <div class="cfm-head"><h3></h3><button class="cfm-x" value="" aria-label="Cerrar">✕</button></div>
                <div class="cfm-body"><p class="cfm-msg"></p><div class="cfm-aviso" hidden></div></div>
                <div class="cfm-foot">
                    <button class="cfm-no" value="">Cancelar</button>
                    <button class="cfm-si" value="ok"></button>
                </div>
            </form>`;
        document.body.appendChild(dlg);
    }

    window.confirmar = function ({ titulo, mensaje, aviso = '', aceptar = 'Aceptar', peligro = false }) {
        if (!dlg) crear();
        dlg.querySelector('h3').textContent        = titulo;
        dlg.querySelector('.cfm-msg').textContent  = mensaje;
        const av = dlg.querySelector('.cfm-aviso');
        av.textContent = aviso;
        av.hidden      = !aviso;
        const si = dlg.querySelector('.cfm-si');
        si.textContent = aceptar;
        si.classList.toggle('peligro', peligro);

        dlg.returnValue = '';
        dlg.showModal();
        (peligro ? dlg.querySelector('.cfm-no') : si).focus();

        return new Promise(resolve => {
            dlg.addEventListener('close', () => resolve(dlg.returnValue === 'ok'), { once: true });
        });
    };
})();
