# Validador de Prestaciones CPMS

Herramienta web local para validar archivos Excel de atenciones de salud CPMS.
Detecta códigos duplicados, redundancias de grupo y códigos no permitidos por tipo de atención,
y genera un archivo Excel marcado con acciones sugeridas y motivos de observación.

## Requisitos

| Requisito | Versión mínima |
|-----------|---------------|
| PHP       | 8.1           |
| ext-zip   | incluida con PHP (puede requerir activación) |
| Composer  | 2.x           |
| Laragon   | 6.x (o cualquier servidor Apache/Nginx local) |

## Instalación en Laragon

### 1. Clonar o copiar el proyecto

Coloca la carpeta del proyecto dentro de `C:\laragon\www\`:

```
C:\laragon\www\validadorPrestaciones\
```

### 2. Activar ext-zip en PHP

1. Abre **Laragon** → clic derecho en el ícono de la bandeja → **PHP** → **php.ini**
2. Busca la línea `;extension=zip` y quita el punto y coma:
   ```ini
   extension=zip
   ```
3. Guarda y reinicia los servicios de Laragon (botón **Reload**).

Para verificar que la extensión está activa:
```powershell
php -m | Select-String zip
```
Debe mostrar `zip`.

### 3. Instalar dependencias

En una terminal dentro de la carpeta del proyecto:

```powershell
composer install
```

Esto descarga `phpoffice/phpspreadsheet` y genera el autoloader en `vendor/`.

### 4. Crear el directorio de almacenamiento temporal

```powershell
mkdir storage
```

El directorio `storage/` es donde se guardan los archivos procesados antes de su descarga.
Ya existe un `.gitkeep` para mantenerlo en el repositorio; los archivos `.xlsx` generados
están en `.gitignore`.

### 5. Acceder a la aplicación

Abre en el navegador:

```
http://validadorPrestaciones.test/
```

o bien

```
http://localhost/validadorPrestaciones/
```

## Uso

1. Abre la URL en el navegador.
2. Arrastra o selecciona el archivo `.xlsx` de atenciones.
   - El archivo debe contener una hoja llamada **DATA**.
   - Debe incluir las columnas: **PK**, **COD. CPMS**, **TIPO DE ATENCIÓN**, **DESCRIPCION CPMS**, **valor**.
3. Haz clic en **Procesar archivo**.
4. Espera el procesamiento (archivos de ~16 000 filas toman ~30 segundos).
5. Revisa las métricas, el resumen por regla y la tabla de detalle filtrable.
6. Descarga el archivo validado con el botón **Descargar**.

> El archivo descargado es de un solo uso: el enlace expira tras la primera descarga
> o después de 1 hora.

## Reglas de validación

| Regla | Color | Prioridad | Descripción |
|-------|-------|-----------|-------------|
| Código 93784 prohibido | 🔴 Rojo `#FFCCCC` | 4 (mayor) | El código 93784 no está permitido en atenciones de tipo 2 o 3 |
| Códigos duplicados | 🟣 Violeta `#E8CCFF` | 3 | Un mismo código CPMS aparece más de una vez en la misma atención |
| Redundancia Hemograma | 🟡 Ámbar `#FFE599` | 2 | Dos o más códigos distintos de hemograma en la misma atención; se conserva el de mayor valor |
| Redundancia Urocultivo | 🩵 Turquesa `#B7E1E4` | 1 | Dos o más códigos distintos de urocultivo en la misma atención; se conserva el de mayor valor |

Cuando una fila activa más de una regla, el color y la acción corresponden a la de mayor prioridad.
Todos los motivos se concatenan con ` || `.

## Estructura del proyecto

```
validadorPrestaciones/
├── assets/
│   ├── app.js          # Lógica JS: dropzone + filtro de tabla
│   └── estilos.css     # Estilos de la interfaz
├── src/
│   ├── config.php      # Parámetros editables (grupos, colores, límites)
│   ├── EscritorExcel.php
│   ├── LectorExcel.php
│   ├── MotorValidacion.php
│   ├── Normalizador.php
│   ├── Observacion.php
│   ├── ResultadoValidacion.php
│   ├── Texto.php
│   └── Reglas/
│       ├── ReglaInterface.php
│       ├── ReglaCodigosDuplicados.php
│       ├── ReglaCodigoNoPermitidoPorTipo.php
│       └── ReglaRedundanciaGrupo.php
├── storage/            # Archivos temporales (excluidos del repo)
│   └── .gitkeep
├── vendor/             # Dependencias Composer (excluidas del repo)
├── composer.json
├── descargar.php       # Entrega el xlsx por token
└── index.php           # Punto de entrada principal
```

## Configuración

Edita `src/config.php` para ajustar:

- **`hoja`**: nombre de la hoja Excel a leer (por defecto `DATA`)
- **`columnas`**: nombres de las columnas a localizar (tolerante a acentos y mayúsculas)
- **`grupos`**: códigos CPMS que forman cada grupo de redundancia
- **`colores`**: colores y prioridades por tipo de regla
- **`limites.memory`**: memoria máxima PHP (por defecto `1G`)
- **`limites.timeout`**: tiempo máximo de ejecución en segundos (por defecto `120`)
- **`limites.max_filas_tabla`**: máximo de filas mostradas en la tabla web (por defecto `5000`)
- **`token_ttl`**: segundos que el archivo procesado permanece disponible (por defecto `3600`)

## Solución de problemas

### "Call to undefined function zip_open()"
La extensión `ext-zip` no está activa. Sigue el paso 2 de instalación.

### "No se encontró la columna PK"
El archivo no tiene una columna con encabezado `PK` en la hoja `DATA`.
Verifica el nombre exacto de las columnas o ajusta `src/config.php`.

### "No se encontró la hoja DATA"
El archivo no contiene una hoja llamada `DATA`.
Ajusta `'hoja'` en `src/config.php` o renombra la hoja en Excel.

### El archivo tarda mucho / memoria insuficiente
Aumenta los límites en `src/config.php`:
```php
'limites' => [
    'memory'  => '2G',
    'timeout' => 300,
],
```

### El directorio storage/ no existe o no es escribible
```powershell
mkdir storage
# En caso de permisos en Linux/Mac:
chmod 775 storage
```
