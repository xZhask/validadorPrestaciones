# Ajuste de UI — Compactación de "Con observación" + estado "revisada"

> **Para Claude Code:** el aplicativo ya existe y funciona. Este es un **ajuste de interfaz** sobre la vista de revisión; **no** se toca el motor de reglas, ni `EscritorExcel`, ni el Excel de salida, ni la lógica de en qué columna cae cada procedimiento. Lee los archivos antes de editar.

## Contexto del código actual (verificado)

- Vista de trabajo: **`revisar.php`** (PHP + HTML + JS inline). Estilos en **`assets/estilos.css`**. API en **`api.php`**.
- `api.php → GET prestacion?id=&pk=` devuelve: `pk, validada, ipress_cod, ipress_nom, tipo, diagnosticos[], con_observacion[], sin_observacion[]`.
  - Cada procedimiento (fila) tiene: `fila` (nº de fila Excel, ancla estable), `codigo`, `desc`, `cantidad`, `valor`.
  - Los de `con_observacion` traen además `observaciones: [{ idx, regla, accion, motivo, color, prioridad, origen }]`.
- Códigos de regla y prioridad reales: `PROHIBIDO_*` (prioridad 5, color `FFCCCC`), `DUPLICADO` (4, `E8CCFF`), `HEMOGRAMA` (3, `FFE599`), `UROCULTIVO` (2, `B7E1E4`), `SUGERENCIA` (1, azul), `MANUAL` (0, `CCCCCC`).
- Render actual en `revisar.php`: `renderDetalle()`, `renderConObs(proc)`, `renderSinObs(proc)`, y un mapa global `_obs[`${fila}_${idx}`]` usado por `abrirEditar()` / `borrarObs()`. Las tarjetas "Con observación" son altas (`.proc-card` con `.obs-list` y footer).

## Problema a resolver

En prestaciones con muchas observaciones (p. ej. el PK `4360749415/02/2025…240100`, con 161 "con obs"), la columna es inmanejable: tarjetas muy altas, texto repetido, botones que compiten, y sin forma de marcar avance. Hay que **compactar y jerarquizar**.

---

## Parte A — Compactación de la columna "Con observación" (solo `revisar.php` + `estilos.css`)

1. **Agrupar las observaciones por regla.** Deriva un *grupo* a partir de `obs.regla`:
   - `PROHIBIDO_*` → **"No permitido"**
   - `DUPLICADO` → **"Duplicados"**, `HEMOGRAMA` → **"Hemograma"**, `UROCULTIVO` → **"Urocultivo"**, `SUGERENCIA` → **"Sugerencia"**, `MANUAL` → **"Manual"**.
   - Como un mismo procedimiento puede tener varias observaciones, agrupa **a nivel de observación** (no de procedimiento): cada observación es un ítem del grupo, llevando consigo su `fila`, `codigo`, `desc`, `cantidad`, `valor`.
   - Ordena los grupos por **prioridad descendente** (No permitido → Duplicados → Hemograma → Urocultivo → Sugerencia → Manual).

2. **Encabezado de grupo colapsable:** chevron + punto de color (usa el `color` hex de la observación del grupo) + nombre + conteo `(N)` (y `· n revisadas` si aplica) + botón **tenue** "marcar grupo revisado" (Parte B).
   - **"Duplicados" colapsado por defecto**; los demás grupos expandidos. Recordar el estado de colapso por grupo mientras dure la sesión de pantalla.

3. **Fila compacta de una sola línea** por observación (reemplaza la tarjeta alta):
   `[check]  F.{fila}  {codigo}  {desc con elipsis}  [chip de acción]  {cantidad}×S/.{valor}  [editar] [eliminar]`
   - El **chip de acción** usa el `color` de la observación y muestra `accion`. Para duplicados, `accion` es `"AGREGAR — cantidad = N"`: muéstralo compacto resaltando el número, p. ej. **`AGREGAR ×N`** (puedes parsear N de la cadena `cantidad = (\d+)`); si no hay match, muestra `accion` tal cual.
   - El **motivo** no se muestra por defecto: aparece al **hacer clic en la fila** (expandir/colapsar una sub-línea con el `motivo`).
   - Los botones **editar (✏)** y **eliminar (✕)** quedan **atenuados y visibles solo en hover/focus** de la fila (no siempre a todo color). Siguen llamando a `abrirEditar(fila, idx)` y `borrarObs(fila, idx)`; mantén el registro en `_obs[`${fila}_${idx}`]`.
   - Conserva la etiqueta `manual` cuando `origen === 'manual'` (discreta).

4. **Quitar el footer por tarjeta** "+ Observación" / "Eliminar CPMS" de los procedimientos **con** observación. Editar/eliminar la observación se hace con los iconos de la fila; agregar/eliminar-CPMS solo tiene sentido en la columna "Sin observación".

5. **Columna "Sin observación":** mantener su funcionalidad (`abrirAgregar`, `doEliminarCpms`), pero **atenuar** los botones "+ Obs" / "Eliminar CPMS" (visibles en hover, no rojo permanente a la vista en decenas de filas). Las filas ya son de una línea; respétalo.

6. **Estilos** (`estilos.css`): añadir/ajustar clases para grupos (`.obs-grupo`, `.obs-grupo-head`, `.chevron`, `.grupo-bulk`), fila compacta (`.obs-fila`, `.obs-chip`, hover de acciones), sub-línea de motivo y estado revisada (atenuado). Reducir el alto de cada ítem ~60% respecto del actual. Mantener el sistema de colores por regla.

**Criterio de aceptación A:** abrir el PK `4360749415/02/2025…240100` muestra los grupos con "Duplicados" colapsado (solo encabezado con su conteo) y los grupos críticos (No permitido / Hemograma / Sugerencia) visibles y recorribles; cada observación ocupa una línea; el motivo aparece al expandir; editar y eliminar siguen funcionando.

---

## Parte B — Estado "revisada" persistente (toca `api.php`, `GestorSesiones`, `estado.json`)

Para no perder el avance entre días, el estado "revisada" debe persistir (coherente con que toda la sesión ya persiste).

1. **Esquema:** cada observación en `estado.json` admite el campo opcional `revisada` (bool, default `false`). No migrar nada: tratar ausencia como `false`.

2. **Endpoints nuevos en `api.php`:**
   - `POST:revisar-obs` — body `{ id, pk, fila, idx }`: alterna `revisada` de esa observación; responde `{ revisada }`.
   - `POST:revisar-grupo` — body `{ id, pk, grupo }`: alterna **todas** las observaciones de esa familia de regla en la prestación (deriva la familia con el **mismo criterio** que el frontend: `PROHIBIDO_*` → "No permitido", etc.); responde el nuevo estado del grupo (p. ej. `{ revisada: true, n: N }`).
   - Reutiliza el patrón existente (`bodyJson`, `req`, `$gestor->cargar/guardar`).
   - `GET prestacion` ya devuelve los campos de cada observación, así que incluirá `revisada` automáticamente.

3. **Frontend (`revisar.php`):**
   - El **check** de cada fila llama a `revisar-obs`; la fila revisada se **atenúa** (clase tipo `.revisada`).
   - El botón **"marcar grupo revisado"** llama a `revisar-grupo` y refresca el grupo.
   - Mostrar un contador **`x/y revisadas`** en el título de la columna "Con observación" (y opcionalmente por grupo).
   - No bloquear el flujo: el estado revisada es ayuda de avance del auditor; **no** afecta el Excel ni la validación de la prestación.

**Criterio de aceptación B:** marcar observaciones/grupos como revisadas, **recargar la página** y reabrir la prestación → el estado revisada persiste; el contador refleja lo revisado.

---

## Restricciones (no romper)
- No modificar `MotorValidacion`, `src/Reglas/*`, `EscritorExcel`, ni el formato del Excel de salida.
- No cambiar en qué columna (con/sin observación) cae cada procedimiento.
- Mantener vanilla JS (sin frameworks), PSR-4, sin dependencias nuevas, sin base de datos.
- `revisada` es metadato de la sesión; **no** debe escribirse en el Excel.

Al terminar, muéstrame los archivos modificados y cómo probar el PK pesado de ejemplo.
