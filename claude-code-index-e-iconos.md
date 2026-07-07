# Ajuste visual — Rediseño del `index.php` + set de iconos fijo

> **Para Claude Code:** ajuste **de apariencia**. No cambia el flujo de procesamiento, ni `api.php`, ni el motor, ni `EscritorExcel`, ni el Excel de salida. La subida y el procesamiento reales siguen igual; solo cambian estilo, estructura de presentación e **iconos**.

## Fuente de verdad del diseño
- **`validador-mockup-index.html`** → apariencia y estructura del inicio.
- **`validador-mockup-compacto.html`** → sistema visual base ya adoptado en `revisar.php` (mismos tokens, tipografías IBM Plex, acento verde).

Coloca ambos en el repo si no están y tómalos como especificación exacta.

---

## ⚠️ Regla obligatoria de iconos (lo más importante)

**No uses ninguna librería de iconos (Lucide, Feather, Font Awesome, Heroicons, Bootstrap Icons, Material, etc.) ni emojis.** Usa **exactamente** los SVG inline que se listan abajo, copiados **verbatim**. No los reemplaces por equivalentes "parecidos": los `path` deben ser idénticos, porque cualquier sustitución rompe la estética que ya tiene la app.

Todos los iconos usan el mismo envoltorio (tamaño vía `width`/`height` o CSS):

```html
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"> … </svg>
```

### Set canónico (nombre → uso → contenido interno del `<svg>`)

| # | Nombre | Uso | Contenido |
|---|--------|-----|-----------|
| 1 | `list` | Enlace "Ver auditorías" (topbar) | `<path d="M3 7h18M3 12h18M3 17h18"/>` |
| 2 | `upload` | Icono del dropzone | `<path d="M12 16V4m0 0l-4 4m4-4l4 4"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>` |
| 3 | `file` | Archivo seleccionado | `<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>` |
| 4 | `close` | Quitar archivo | `<path d="M18 6 6 18M6 6l12 12"/>` |
| 5 | `arrow-right` | "Procesar archivo" / "Abrir revisión" | `<path d="M5 12h14M13 6l6 6-6 6"/>` |
| 6 | `chevron-right` | Toggle "Requisitos del archivo" | `<path d="M9 6l6 6-6 6"/>` |
| 7 | `check` | Estado "procesado" y check de validada | `<path d="M20 6 9 17l-5-5"/>` |
| 8 | `download` | "Descargar Excel" | `<path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/>` |
| 9 | `chart` | Título "Auditorías recientes" | `<path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 4-5"/>` |
| 10 | `circle-check` | Sesión completada | `<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14.5l-3-3"/>` |
| 11 | `clock` | Sesión en curso | `<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>` |

> Para la **vista de revisión** (`revisar.php`) usa estos mismos, verbatim, si en algún punto quedaron con otro icono:

| # | Nombre | Uso | Contenido |
|---|--------|-----|-----------|
| 12 | `alert` | Cabecera "Con observación" | `<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>` |
| 13 | `edit` | Editar observación | `<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>` |
| 14 | `trash` | Eliminar observación | `<path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/>` |
| 15 | `chevron-down` | Colapso de grupo | `<path d="M6 9l6 6 6-6"/>` |
| 16 | `plus` | "+ Obs" / agregar observación | `<path d="M12 5v14M5 12h14"/>` |
| 17 | `circle` | Prestación pendiente (sidebar) | `<circle cx="12" cy="12" r="9"/>` |
| 18 | `check` (nº 7) | "Sin observación" y validada | `<path d="M20 6 9 17l-5-5"/>` |

**El glifo "V"** de la marca es texto en un cuadro verde (no un icono): mantenlo como en el mockup.

---

## Rediseño del `index.php` (según `validador-mockup-index.html`)

1. **Topbar** idéntica a la del resto de la app: glifo verde "V" + "Validador de atenciones" / "DIRSAPOL · control de calidad CPMS"; a la derecha, enlace **"Ver auditorías"** con el icono `list` (#1). Quitar la cabecera navy actual.
2. **Encabezado** "Nueva validación" + subtítulo.
3. **Layout en dos columnas** (responsive a una): izquierda la carga, derecha "Auditorías recientes".
4. **Dropzone limpio:** icono `upload` (#2) —nada de emoji de carpeta—, texto "Arrastra tu **.xlsx** aquí o haz clic para seleccionar" y subtexto (hoja «DATA», ~16 000 filas). En hover/drag, borde y fondo en verde suave.
5. **Estado de archivo seleccionado:** tarjeta con icono `file` (#3), nombre, tamaño, chip "Hoja: DATA" y botón `close` (#4) para quitar. Al seleccionar, habilitar "Procesar archivo" (verde).
6. **Requisitos plegables** (fuera del dropzone): botón "Requisitos del archivo" con `chevron-right` (#6) que rota; al abrir, dos grupos — **Requeridas** (chips en verde) y **Opcionales** (chips grises), con los nombres reales de columnas.
7. **Botones:** "Procesar archivo" (primario verde, icono `arrow-right` #5, **deshabilitado** hasta que haya archivo) y "Limpiar".
8. **Feedback de procesamiento:** al procesar, mostrar spinner + mensajes por pasos ("Leyendo archivo…", "Detectando columnas…", "Agrupando 514 prestaciones…", "Aplicando reglas…", "Generando reporte…"). Es necesario porque el proceso real tarda varios segundos.
9. **Estado final:** icono `check` (#7) + resumen ("N prestaciones · M observaciones") + botones "Abrir revisión" (`arrow-right`) y "Descargar Excel" (`download` #8). La descarga queda activa de inmediato.
10. **Panel "Auditorías recientes":** título con icono `chart` (#9) + enlace "Ver todas"; hasta 3 sesiones con icono de estado (`circle-check` #10 completada / `clock` #11 en curso), nombre, fecha, barra de progreso, "X/Y" y botón "Continuar"/"Ver". Los datos salen de `GestorSesiones->listar()`.

Los estilos (tokens, tipografías, colores, radios) deben venir del sistema del mockup; reutiliza `assets/estilos.css` para no duplicar.

---

## Restricciones
- Solo CSS + markup de presentación e iconos. No tocar `api.php`, motor, reglas, `EscritorExcel`, ni el Excel de salida ni el flujo de subida/proceso.
- Mantener funcional: seleccionar/soltar archivo, procesar, limpiar, y el listado real de auditorías recientes.
- Vanilla JS, sin frameworks ni dependencias nuevas.
- **Iconos: exclusivamente el set de arriba, verbatim.** Si en `revisar.php` hay iconos distintos a los del set, alinéalos también.

## Aceptación
- `index.php` se ve como `validador-mockup-index.html`: topbar unificada, dropzone con icono `upload`, requisitos plegables, feedback por pasos y panel de auditorías recientes.
- **Todos** los iconos de la app (inicio y revisión) son exactamente los del set canónico; no aparece ningún icono de librería externa ni emoji.

Al terminar, lista los archivos modificados y confirma que no se introdujo ninguna librería de iconos.
