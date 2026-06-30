# Guía de actualización — Validador CPMS

Cuando hay una nueva versión del código, **no es necesario repetir todo el proceso
de distribución**. La carpeta `php\` y `vendor\` no cambian en una actualización
normal de código, así que el parche es mucho más liviano.

---

## Qué cambia en cada tipo de actualización

| Qué cambió | Archivos afectados | ¿Necesitas rehacer `php\`? | ¿Necesitas rehacer `vendor\`? |
|---|---|---|---|
| Solo código (lo más común) | `src\`, `assets\`, archivos `.php` raíz | No | No |
| Nueva dependencia PHP (`composer require ...`) | `vendor\` + `composer.lock` | No | Sí |
| Cambio de versión PHP | `php\` | Sí | No |

---

## CASO 1 — Actualización de código (el más frecuente)

### En tu PC

1. Aplica los cambios en el código normalmente (editar archivos, git pull, etc.).
2. Abre `iniciar.bat` y prueba que la nueva versión funciona.
3. Crea un ZIP de parche con **solo los archivos que cambiaron**:

   ```
   Parche_ValidadorCPMS_vX.X\
   ├── index.php          ← si cambió
   ├── api.php            ← si cambió
   ├── revisar.php        ← si cambió
   ├── descargar.php      ← si cambió
   ├── auditorias.php     ← si cambió
   ├── src\               ← si cambió algo dentro
   └── assets\            ← si cambió algo dentro
   ```

   > Si no sabes exactamente qué cambió, incluye todos los archivos `.php` de la
   > raíz más las carpetas `src\` y `assets\`. Pesan poco y no hay riesgo.
   >
   > **Nunca incluyas** en el parche: `php\`, `vendor\`, `storage\`, `logs\`.

4. Entrega el ZIP al usuario (USB, email, Drive).

---

### En la PC del usuario

1. **Cierra la aplicación**: cierra la ventana negra de consola si está abierta.
2. Descomprime el ZIP del parche.
3. Copia y **reemplaza** los archivos/carpetas del parche dentro de la carpeta
   `ValidadorCPMS\` existente.

   > Si Windows pregunta "¿Desea reemplazar los archivos?", responde **Sí a todo**.

4. Vuelve a hacer doble clic en `iniciar.bat`.

**Listo.** Los datos de sesiones anteriores en `storage\` no se tocan.

---

## CASO 2 — Se agregó una nueva dependencia PHP (`vendor\` cambió)

Esto ocurre si corriste `composer require` o `composer update` en tu PC.

### En tu PC

1. Confirma que ejecutaste `composer install` o `composer update` y que `vendor\`
   está actualizado.
2. Crea el ZIP del parche igual que en el Caso 1, pero **agrega la carpeta `vendor\`**:

   ```
   Parche_ValidadorCPMS_vX.X\
   ├── (archivos .php que cambiaron)
   ├── src\
   ├── assets\
   └── vendor\            ← INCLUIR en este caso
   ```

   > `vendor\` puede pesar varios MB. Si el usuario tiene conexión lenta, avísale.

### En la PC del usuario

Mismos pasos que el Caso 1. Al reemplazar, `vendor\` se actualiza completo.

---

## CASO 3 — Cambió la versión de PHP

Esto es poco frecuente y solo ocurre si el código requiere una versión PHP diferente.

En ese caso sí debes repetir el **Paso 1 al 3 de la GUIA_DISTRIBUCION.md** para
descargar el nuevo PHP portátil y reconfigurar `php\php.ini`. Luego crea el ZIP
completo como la primera vez.

---

## Referencia rápida — ¿Qué incluir en el parche?

```
SIEMPRE en el parche:
  ✓ Archivos .php de la raíz que cambiaron
  ✓ src\    (si cambió algo)
  ✓ assets\ (si cambió algo)

SOLO si cambió:
  ✓ vendor\ (si corriste composer require/update)

NUNCA en el parche:
  ✗ php\       (no cambia con el código)
  ✗ storage\   (son datos del usuario — se borrarían)
  ✗ logs\      (ídem)
```

---

## Consejo: versionar los parches

Nombra los ZIPs con versión y fecha para llevar un historial:

```
Parche_ValidadorCPMS_v1.1_2026-07-01.zip
Parche_ValidadorCPMS_v1.2_2026-07-15.zip
```
