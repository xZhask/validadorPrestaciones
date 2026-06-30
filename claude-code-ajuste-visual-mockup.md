# Ajuste visual — Alinear la apariencia de la app al mockup

> **Para Claude Code:** este ajuste es **solo de apariencia (CSS + markup estático/menor en el render)**. La lógica, los endpoints (`api.php`), el motor de reglas, `EscritorExcel` y el Excel de salida **no cambian**. El comportamiento de la vista de revisión (grupos, colapso, contador de revisadas, editar/eliminar, eliminar-CPMS, validar, filtros) ya funciona y debe seguir funcionando.

## Objetivo

La vista `revisar.php` ya tiene el comportamiento correcto pero conserva el estilo antiguo. Hay que llevar su **apariencia** a la del mockup compacto.

## Fuente de verdad del diseño

Usa **`validador-mockup-compacto.html`** (colócalo en la raíz del repo si no está) como **especificación visual exacta**: toma de su bloque `<style>` el sistema de diseño (tokens CSS, tipografías, colores, radios, espaciados) y de su markup la estructura de cada componente. El objetivo es que `revisar.php` se vea igual que ese archivo. Reemplaza/ajusta `assets/estilos.css` (y el `<style>` inline de `revisar.php`) para adoptar ese sistema.

### Fundamentos a adoptar (del mockup)
- **Tipografías:** importar IBM Plex Sans + IBM Plex Mono (Google Fonts). Texto general en IBM Plex Sans; códigos CPMS, PK, `F.n` y montos en IBM Plex Mono.
- **Tokens** (`:root`): paleta clara con acento verde institucional (`--accent:#1f7a52`), fondo `--bg:#eef1f4`, superficies blancas, líneas suaves, y los colores por regla (duplicados violeta, hemograma ámbar, urocultivo turquesa, no permitido rojo, sugerencia azul, manual gris). Radios ~8px.

## Cambios por zona (de la captura actual → mockup)

1. **Barra superior (topbar):** reemplazar la cabecera navy con título "Revisión — …" por la topbar blanca del mockup:
   - Izquierda: glifo cuadrado verde **"V"** + "Validador de atenciones" / subtítulo "DIRSAPOL · control de calidad CPMS".
   - El **filtro de IPRESS** se mueve a la topbar (no al sidebar).
   - Derecha: medidor **"N / 514 validadas"** + barra de progreso + botón verde **"Descargar Excel"**.
   - Conserva un acceso **discreto** de regreso a Auditorías (p. ej. enlace pequeño junto al glifo o glifo clicable); ya no como línea prominente con el id de sesión.

2. **Sidebar de prestaciones:** cabecera "PRESTACIONES", buscador, segmentado **Todas / Pendientes / Validadas** (activo en píldora oscura), y un conteo "514 prestaciones". Cada ítem:
   - Icono de estado circular (círculo vacío = pendiente; check verde = validada).
   - **PK en mono** (con elipsis), debajo un **chip corto de IPRESS** (solo "Arequipa" / "Chiclayo" / "ABL" / "Geriátrico", derivado de `ipress_nom`) y **"N obs." en verde** (no el pill amarillo actual).
   - Ítem seleccionado con fondo verde suave.

3. **Cabecera del detalle:** PK en mono grande; debajo una fila de **pills** (`ipress_nom`, `Tipo N`, fechas si existen) y los **diagnósticos también como pills** (`código` en mono + descripción). Botón **"Marcar como validada"** arriba a la derecha con icono de check (estilo verde cuando está validada).

4. **Columna "Con observación":** encabezado con icono de **triángulo de aviso** + "Con observación" + a la derecha **"x/y revisadas"**. Grupos como en el mockup:
   - Encabezado de grupo: **chevron** + **cuadrito de color** de la regla + nombre + **(conteo)** + a la derecha el botón **tenue** "marcar grupo revisado".
   - **Filas compactas de una línea:** `[check] F.n  codigo(mono)  desc(elipsis)  [chip de acción de color]  cantidad × S/.valor(mono, tenue)` con los iconos **editar/eliminar atenuados, visibles en hover**. El **motivo** se revela al expandir la fila.
   - Chip de acción: `ELIMINAR` / `SUGERENCIA` / para duplicados **`AGREGAR ×N`** (resaltando el número), usando el color de la regla.
   - Filas marcadas como revisadas: **atenuadas** + check lleno.

5. **Columna "Sin observación":** encabezado con icono de **check** + "N (+M restantes)". Filas de una línea (`F.n`, código mono, desc, monto mono) con botones **"+ Obs"** y **"Eliminar CPMS"** **tenues**, realzados solo en hover (no en rojo permanente en decenas de filas).

6. **Diálogo de observación (agregar/editar)** y **toasts:** alinéalos al mismo sistema (tipografías, colores, radios, botón primario verde).

## Restricciones (no romper)
- Solo CSS y markup de presentación. No tocar `api.php`, `MotorValidacion`, `src/Reglas/*`, `GestorSesiones`, `EscritorExcel`, ni el Excel de salida.
- Mantener intactos: búsqueda, filtro IPRESS, segmentado de estado, progreso, descarga, colapso de grupos, marcar revisada (individual y por grupo), editar/eliminar observación, eliminar-CPMS y validar prestación.
- Vanilla JS, sin frameworks ni dependencias nuevas.
- Si algún dato del mockup no existe en el backend (p. ej. fechas de la prestación), omite ese pill en vez de inventarlo o agregar lógica nueva.

## Aceptación
- `revisar.php` luce como `validador-mockup-compacto.html`: misma topbar, sidebar, cabecera de detalle con pills, grupos con cuadrito de color y chevron, filas compactas con chips de color, y columna "Sin observación" con acciones tenues.
- Probar con el PK pesado `4360749415/02/2025…240100` (≈182 obs): la columna "Con observación" se ve agrupada y compacta como el mockup; todo el comportamiento previo sigue operando.

Al terminar, muéstrame los archivos modificados y una captura/descripción del resultado.
