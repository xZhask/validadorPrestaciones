// Validador CPMS

(function () {
    'use strict';

    // ── Upload zone ────────────────────────────────────────────────────────
    const input = document.getElementById('archivoInput');
    const label = document.getElementById('dropLabel');
    const btn   = document.getElementById('btnProcesar');
    const drop  = document.getElementById('dropZone');
    const form  = document.getElementById('uploadForm');

    if (input) {
        input.addEventListener('change', () => {
            const nombre = input.files[0]?.name ?? '';
            if (nombre) {
                label.textContent = '📄 ' + nombre;
                btn.disabled = false;
            } else {
                label.innerHTML = 'Arrastra tu <strong>.xlsx</strong> aquí o haz clic para seleccionar';
                btn.disabled = true;
            }
        });

        ['dragenter', 'dragover'].forEach(ev =>
            drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('drag-over'); })
        );
        ['dragleave', 'drop'].forEach(ev =>
            drop.addEventListener(ev, () => drop.classList.remove('drag-over'))
        );

        form.addEventListener('submit', () => {
            btn.disabled = true;
            btn.textContent = 'Procesando…';
        });
    }

    // ── Results table filter ───────────────────────────────────────────────
    const tabla     = document.getElementById('tablaObs');
    const busqueda  = document.getElementById('busqueda');
    const chipGroup = document.getElementById('chipGroup');
    const contador  = document.getElementById('filtroContador');

    if (!tabla) return;

    const filas = Array.from(tabla.querySelectorAll('tbody tr'));
    const chips = chipGroup ? Array.from(chipGroup.querySelectorAll('[data-regla]')) : [];

    let filtroTexto = '';
    let filtroRegla = '';

    function aplicarFiltros() {
        let visibles = 0;
        for (const tr of filas) {
            const matchTexto = !filtroTexto || tr.dataset.search.includes(filtroTexto);
            const matchRegla = !filtroRegla || tr.dataset.regla === filtroRegla;
            tr.hidden = !(matchTexto && matchRegla);
            if (!tr.hidden) visibles++;
        }
        if (contador) {
            contador.textContent = 'Mostrando ' + visibles.toLocaleString('es-PE')
                + ' de ' + filas.length.toLocaleString('es-PE');
        }
    }

    // Inicializar contador sin filtros
    if (contador) {
        contador.textContent = 'Mostrando ' + filas.length.toLocaleString('es-PE')
            + ' de ' + filas.length.toLocaleString('es-PE');
    }

    // Búsqueda de texto con debounce
    let timer;
    if (busqueda) {
        busqueda.addEventListener('input', () => {
            filtroTexto = busqueda.value.trim().toLowerCase();
            clearTimeout(timer);
            timer = setTimeout(aplicarFiltros, 180);
        });
    }

    // Filtro por regla (chips)
    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            filtroRegla = chip.dataset.regla;
            chips.forEach(c => c.classList.toggle('active', c === chip));
            aplicarFiltros();
        });
    });

})();
