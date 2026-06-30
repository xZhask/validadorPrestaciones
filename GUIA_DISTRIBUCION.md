# Guía de distribución portátil — Validador CPMS

Esta guía te lleva, paso a paso, desde tu PC de desarrollo hasta dejar la aplicación
funcionando en la PC del usuario final **sin que éste necesite instalar nada**.

El mecanismo es simple: se empaqueta PHP portátil junto con el código. El usuario
solo hace doble clic en `iniciar.bat` y el navegador abre la aplicación.

---

## Resumen de lo que hay que hacer

| Etapa | Dónde | Qué |
|-------|-------|-----|
| 1 | Tu PC | Descargar PHP portátil y colocarlo en la carpeta `php/` |
| 2 | Tu PC | Configurar `php/php.ini` con las extensiones necesarias |
| 3 | Tu PC | Probar que `iniciar.bat` funciona localmente |
| 4 | Tu PC | Armar el ZIP de distribución |
| 5 | PC usuario | Descomprimir el ZIP |
| 6 | PC usuario | Doble clic en `iniciar.bat` |

---

## PARTE 1 — En tu PC (preparar el paquete)

### Paso 1 — Descargar PHP portátil para Windows

1. Abre el navegador y ve a: **https://windows.php.net/download/**
2. Busca la sección **PHP 8.3** (es la versión estable recomendada para este proyecto).
3. Descarga el archivo que diga exactamente:
   - **VS16 x64 Non Thread Safe** → columna `Zip`
   - El archivo se llama algo como `php-8.3.XX-nts-Win32-vs16-x64.zip`

   > **¿Por qué esta versión?** El proyecto requiere PHP 8.3 o superior (lo exigen las
   > dependencias). La versión NTS (Non Thread Safe) es la correcta para el servidor
   > interno que usa `iniciar.bat`.

4. Guarda el ZIP en cualquier lugar temporal (Escritorio, Descargas, etc.).

---

### Paso 2 — Crear la carpeta `php/` dentro del proyecto

1. Abre la carpeta del proyecto:
   ```
   C:\laragon\www\validadorPrestaciones\
   ```
2. Crea una nueva carpeta llamada exactamente `php` (en minúsculas).
3. Abre el ZIP de PHP que descargaste y **extrae todo su contenido** dentro de esa carpeta `php/`.

   La estructura debe quedar así:
   ```
   validadorPrestaciones\
   └── php\
       ├── php.exe          ← el ejecutable principal
       ├── php8ts.dll
       ├── ext\             ← extensiones (carpeta importante)
       │   ├── php_zip.dll
       │   ├── php_mbstring.dll
       │   └── ... (muchos más)
       └── ... (otros archivos)
   ```

---

### Paso 3 — Configurar `php/php.ini`

El proyecto incluye una plantilla de configuración lista para copiar.

1. Ve a la carpeta del proyecto y localiza el archivo `php.ini.dist`.
2. Cópialo dentro de la carpeta `php\` que acabas de crear.
3. Renómbralo a `php.ini` (quita el `.dist`).

   Resultado:
   ```
   validadorPrestaciones\
   └── php\
       ├── php.exe
       ├── php.ini      ← recién copiado y renombrado
       └── ext\
   ```

4. Abre `php\php.ini` con el Bloc de notas (o VS Code) y verifica que tenga estas líneas
   sin el punto y coma inicial (`;`). Si alguna tiene `;` al inicio, quítalo:

   ```ini
   extension_dir = "ext"

   extension=zip
   extension=fileinfo
   extension=mbstring
   extension=gd
   ```

   El archivo `php.ini.dist` ya viene con esto configurado, así que normalmente
   no necesitas hacer cambios.

---

### Paso 4 — Probar localmente con `iniciar.bat`

Antes de empaquetar, verifica que todo funciona:

1. Ve a la carpeta del proyecto.
2. Haz doble clic en `iniciar.bat`.
3. Debe abrirse una ventana negra de consola con el mensaje:
   ```
   ============================================
     Validador CPMS — Servidor local
     http://localhost:8080
   ============================================
   ```
4. El navegador debe abrir automáticamente `http://localhost:8080` con la aplicación.
5. Sube un archivo `.xlsx` de prueba para confirmar que todo funciona.
6. **Para detener el servidor**: cierra la ventana negra de consola.

   > Si aparece un error en la consola, léelo con atención. Los más comunes:
   > - `No se encontro php\php.exe` → la carpeta `php\` está mal ubicada o falta `php.exe`
   > - `Puerto 8080 ya en uso` → ya hay una instancia corriendo; el navegador se abre solo

---

### Paso 5 — Armar el ZIP de distribución

Una vez confirmado que funciona, crea el paquete para el usuario.

#### Carpetas y archivos que SÍ deben ir en el ZIP

```
ValidadorCPMS\                ← nombre sugerido para la carpeta raíz del ZIP
├── iniciar.bat
├── index.php
├── api.php
├── revisar.php
├── descargar.php
├── auditorias.php
├── php\                      ← PHP portátil completo
├── src\                      ← clases del validador
├── assets\                   ← estilos y scripts web
├── vendor\                   ← dependencias (¡OBLIGATORIO incluir!)
├── storage\                  ← debe existir como carpeta vacía
└── logs\                     ← debe existir como carpeta vacía
```

#### Carpetas y archivos que NO van en el ZIP

```
.git\               ← historial de git (innecesario para el usuario)
.gitignore
.claude\
composer.json
composer.lock
php.ini.dist        ← ya fue copiado como php\php.ini
README.md
GUIA_DISTRIBUCION.md
claude-code-*.md    ← archivos internos de desarrollo
*.html              ← mockups internos
```

#### Cómo crear el ZIP

**Opción A — Desde el Explorador de Windows (sin programas extra):**

1. Crea una nueva carpeta en el Escritorio llamada `ValidadorCPMS`.
2. Copia dentro de ella exactamente las carpetas y archivos de la lista anterior.
3. Haz clic derecho sobre la carpeta `ValidadorCPMS` → **Comprimir en archivo ZIP**.
4. El archivo `ValidadorCPMS.zip` es el que entregas al usuario.

**Opción B — Con 7-Zip (si lo tienes instalado):**

1. Selecciona la carpeta del proyecto, clic derecho → **7-Zip → Agregar al archivo**.
2. Excluye manualmente las carpetas no necesarias.

---

## PARTE 2 — En la PC del usuario final

### Requisito del sistema del usuario

- Windows 10 o Windows 11 (64 bits)
- No necesita instalar ningún programa adicional
- Necesita conexión a internet solo si descarga el ZIP por email o Drive

### Paso 1 — Recibir y descomprimir el ZIP

1. El usuario recibe el archivo `ValidadorCPMS.zip` (por USB, email, Drive, etc.).
2. Hace clic derecho sobre el ZIP → **Extraer todo...** → elige dónde (ej. Escritorio o Documentos).
3. Queda la carpeta `ValidadorCPMS\` con todos los archivos adentro.

   > **Importante**: la aplicación debe correr desde la carpeta descomprimida, NO desde
   > adentro del ZIP. Si el usuario intenta ejecutar directamente desde el ZIP sin extraer,
   > no funcionará.

### Paso 2 — Ejecutar la aplicación

1. Abre la carpeta `ValidadorCPMS\`.
2. Haz doble clic en `iniciar.bat`.
3. Es posible que Windows muestre una advertencia de seguridad:
   - **"Windows protegió su PC"** (SmartScreen) → clic en **"Más información"** → **"Ejecutar de todas formas"**
   - **Firewall de Windows** → clic en **"Permitir acceso"**
4. Se abre una ventana negra y luego el navegador con la aplicación en `http://localhost:8080`.

### Paso 3 — Usar la aplicación

1. Selecciona o arrastra un archivo `.xlsx` al área de carga.
2. Espera a que el sistema valide (puede tardar según el tamaño del archivo).
3. Descarga el archivo resultante con el botón de descarga.

### Paso 4 — Cerrar la aplicación

- Cierra la ventana negra de consola para detener el servidor.
- El navegador puede quedar abierto pero la aplicación ya no responderá hasta volver
  a ejecutar `iniciar.bat`.

---

## Solución de problemas comunes

### "No se encontro php\php.exe"
La carpeta `php\` no existe o está vacía. Verifica que el ZIP de PHP fue extraído
correctamente dentro de la subcarpeta `php\` y que `php.exe` está ahí.

### "Puerto 8080 ya en uso"
Ya hay una instancia de la aplicación corriendo. El script detecta esto y abre el
navegador directamente. Si el navegador no abre, escribe manualmente `http://localhost:8080`.

### El navegador muestra "Este sitio no es accesible"
Espera 2-3 segundos y recarga la página. El servidor PHP tarda un momento en iniciar.

### "Windows protegió su PC" (SmartScreen)
Es normal la primera vez que se ejecuta un archivo `.bat` descargado. Clic en
**"Más información"** → **"Ejecutar de todas formas"**.

### La aplicación carga pero da error al subir el archivo
Verifica en la carpeta `php\php.ini` que las extensiones estén habilitadas (sin `;` al inicio).
Las necesarias son: `zip`, `fileinfo`, `mbstring`, `gd`.

### Error "Call to undefined function" o similar al cargar Excel
Falta alguna extensión PHP. Abre `php\php.ini` y asegúrate de que estas líneas existen
y no tienen `;` al inicio:
```ini
extension=zip
extension=fileinfo
extension=mbstring
extension=gd
```

---

## Referencia rápida — Estructura final del ZIP

```
ValidadorCPMS\
├── iniciar.bat          ← DOBLE CLIC AQUÍ PARA INICIAR
├── index.php
├── api.php
├── revisar.php
├── descargar.php
├── auditorias.php
├── php\
│   ├── php.exe
│   ├── php.ini
│   └── ext\
│       ├── php_zip.dll
│       ├── php_mbstring.dll
│       ├── php_fileinfo.dll
│       ├── php_gd.dll
│       └── ...
├── src\
│   ├── config.php
│   ├── Logger.php
│   ├── LectorExcel.php
│   ├── EscritorExcel.php
│   ├── MotorValidacion.php
│   └── Reglas\
├── assets\
│   └── ...
├── vendor\
│   └── ...
├── storage\
└── logs\
```
