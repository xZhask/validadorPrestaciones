# Ajuste visual — Alinear `auditorias.php` al sistema de los otros módulos

> **Para Claude Code:** cambio **solo de presentación** en `auditorias.php`. No se toca `api.php`, `GestorSesiones`, el motor, los datos de sesión, ni `descargar.php`. El listado sigue mostrando exactamente los mismos datos que hoy entrega `$gestor->listar()`.

## Problema
`auditorias.php` quedó con el estilo antiguo (cabecera navy `.site-header` de `assets/estilos.css` + tabla densa) y **desentona** con `index.php` y `revisar.php`, que ya usan el sistema nuevo (topbar blanca con glifo verde "V", tipografías IBM Plex, tokens `--accent/--surface/--line/--faint/--mono`, modo oscuro `data-theme`).

## Fuente de verdad del diseño
- **`validador-mockup-auditorias.html`** (colócalo en el repo) → apariencia y estructura objetivo.
- **`revisar.php`** → de ahí se **reutiliza tal cual** el bloque de tokens `:root` (claro y oscuro), la topbar (`.topbar`/`.brand`/`.glyph`), el botón de tema (`.theme-btn-ix` + `id="themeToggle"`) y las tipografías. No inventes tokens nuevos: usa los mismos.

## Cambios en `auditorias.php`

1. **`<head>`:** importa IBM Plex (Sans + Mono), mantén `<script src="assets/theme.js"></script>`, y **replica el bloque `:root` claro/oscuro y los estilos base** que usa `revisar.php` (tokens, `body`, `.topbar`, `.brand`, `.glyph`, `.theme-btn-ix`). Puedes conservar `assets/estilos.css` si no estorba, pero los estilos de esta vista deben venir del sistema nuevo. Elimina los estilos propios viejos (`.site-header`, `.badge-pendiente/.badge-completa` amarillos, `.progress-bar-wrap`, `.td-id`) y reemplázalos por los del mockup.

2. **Topbar** (reemplaza `<header class="site-header">…`): igual a la de `revisar.php`:
   ```html
   <header class="topbar">
     <a class="brand" href="index.php" title="Volver al validador">
       <div class="glyph">V</div>
       <div><b>Validador de atenciones</b><span>DIRSAPOL · control de calidad CPMS</span></div>
     </a>
     <div class="spacer"></div>
     <button class="theme-btn-ix" id="themeToggle" type="button" aria-label="Cambiar tema"></button>
   </header>
   ```
   (Mantén `id="themeToggle"` para que `theme.js` siga funcionando.)

3. **Encabezado de página** dentro de un contenedor centrado (`max-width ~960px`): título "Auditorías" + subtítulo "Sesiones de auditoría registradas. Cada subida genera una sesión independiente.", y una fila con el botón **"← Volver al validador"** (outline, icono `arrow-left`) y el **conteo** de sesiones (`N sesión/es registrada/s`).

4. **Listado como tarjetas** (una por sesión), no como tabla. Por cada `$s`:
   - **Icono de estado:** `circle-check` (verde) si `validadas >= total && total > 0` (Completa), si no `clock` (tenue, En curso).
   - **Nombre del archivo** (`$s['archivo']`) en negrita + **badge** de estado ("Completa" verde / "En curso" ámbar).
   - **Meta** (tenue): `<?= number_format($s['total']) ?> prestaciones · <fecha creada d/m/Y H:i> · <id 8 chars>…` (el id en mono, con `title` del id completo).
   - **Chips cortos de IPRESS**: deriva un nombre corto de cada `$s['ipress'][n]['nombre']` (ver helper abajo); si no matchea, usa el nombre completo.
   - **Barra de progreso** (`width: $s['progreso']%`) + texto `validadas / total (progreso%)`.
   - **Botón "Revisar →"** (icono `arrow-right`) a `revisar.php?id=<id>`; en hover se pone verde.
   - Conserva el formateo de fecha actual (try/catch con `DateTimeImmutable`).

5. **Estado vacío** (cuando no hay sesiones): tarjeta centrada con el icono `list` (verbatim), "No hay sesiones de auditoría todavía." y enlace a `index.php`.

### Helper de IPRESS corta (PHP)
Deriva la etiqueta a partir del nombre, sin depender de tildes/mayúsculas:
- contiene `AREQUIPA` → `Arequipa`
- contiene `CHICLAYO` → `Chiclayo`
- contiene `LEGUIA` → `ABL`
- contiene `GERIATRICO` o `SAN JOSE` → `Geriátrico`
- en otro caso → el nombre completo tal cual.

## Iconos — usar el set canónico verbatim (no librerías, no emojis)
Envoltorio estándar: `viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"`.
- `arrow-left` (Volver): `<path d="M19 12H5M11 6l-6 6 6 6"/>`
- `arrow-right` (Revisar): `<path d="M5 12h14M13 6l6 6-6 6"/>`
- `clock` (En curso): `<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>`
- `circle-check` (Completa): `<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14.5l-3-3"/>`
- `list` (estado vacío): `<path d="M3 7h18M3 12h18M3 17h18"/>`

Los iconos de sol/luna del toggle los sigue poniendo `theme.js`; no los cambies.

## Restricciones
- Solo `auditorias.php` (markup + estilos de esa vista). No tocar `api.php`, `GestorSesiones`, motor, datos ni `descargar.php`.
- Reutilizar los tokens/topbar de `revisar.php`; no crear un sistema visual paralelo.
- Vanilla, sin frameworks ni dependencias nuevas. Mantener el modo oscuro operativo vía `theme.js`.
- Iconos exclusivamente del set de arriba, verbatim.

## Aceptación
- `auditorias.php` se ve como `validador-mockup-auditorias.html` y es consistente con `index.php`/`revisar.php`: misma topbar con glifo "V", tipografías IBM Plex, tarjetas de sesión con chips de IPRESS, badges de estado y barra de progreso.
- El **modo oscuro** funciona con el mismo botón que el resto (`theme.js`).
- Los datos mostrados son los mismos que hoy; "Revisar →" abre la sesión correcta; el estado vacío y el conteo funcionan.
- No se introdujo ninguna librería de iconos ni emoji.

Al terminar, lista los archivos modificados.
