<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/construirMotor.php';

use Validador\GestorSesiones;
use Validador\LectorExcel;

$cfg    = require __DIR__ . '/src/config.php';
$gestor = new GestorSesiones($cfg['storage_dir']);

// ── Helpers ───────────────────────────────────────────────────────────────────

function jsonOk(mixed $data = null): never
{
    echo json_encode(
        ['ok' => true, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function jsonError(string $msg, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(
        ['ok' => false, 'error' => $msg],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function bodyJson(): array
{
    static $body = null;
    if ($body === null) {
        $raw  = (string) file_get_contents('php://input');
        $dec  = json_decode($raw, true);
        $body = is_array($dec) ? $dec : [];
    }
    return $body;
}

function req(array $source, string $key): string
{
    if (!isset($source[$key]) || $source[$key] === '') {
        jsonError("Campo requerido: {$key}");
    }
    return (string) $source[$key];
}

// ── Router ────────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$ruta   = $_GET['ruta'] ?? '';

try {
    match ("{$method}:{$ruta}") {
        'GET:sesiones'        => rutaGetSesiones($gestor),
        'POST:sesiones'       => rutaPostSesiones($gestor, $cfg),
        'GET:sesion'          => rutaGetSesion($gestor),
        'DELETE:sesion'       => rutaDeleteSesion($gestor),
        'GET:prestacion'      => rutaGetPrestacion($gestor),
        'POST:observacion'    => rutaPostObservacion($gestor),
        'PUT:observacion'     => rutaPutObservacion($gestor),
        'DELETE:observacion'  => rutaDeleteObservacion($gestor),
        'POST:eliminar-cpms'  => rutaPostEliminarCpms($gestor),
        'POST:validar'        => rutaPostValidar($gestor),
        'POST:revisar-obs'    => rutaPostRevisarObs($gestor),
        'POST:revisar-grupo'  => rutaPostRevisarGrupo($gestor),
        'POST:revisar-varias' => rutaPostRevisarVarias($gestor),
        'POST:solicitud-dx'   => rutaGuardarSolicitudDx($gestor, null),
        'PUT:solicitud-dx'    => rutaGuardarSolicitudDx($gestor, (int) (bodyJson()['idx'] ?? -1)),
        'DELETE:solicitud-dx' => rutaDeleteSolicitudDx($gestor),
        'POST:revalidar'      => rutaPostRevalidar($gestor, $cfg),
        default               => jsonError("Ruta no encontrada: {$method} {$ruta}", 404),
    };
} catch (\Throwable $e) {
    jsonError($e->getMessage(), 500);
}

// ── GET sesiones ──────────────────────────────────────────────────────────────

function rutaGetSesiones(GestorSesiones $gestor): never
{
    jsonOk($gestor->listar());
}

// ── POST sesiones ─────────────────────────────────────────────────────────────

function rutaPostSesiones(GestorSesiones $gestor, array $cfg): never
{
    if (empty($_FILES['archivo'])) {
        jsonError('No se recibió archivo (campo multipart: archivo).');
    }

    $file = $_FILES['archivo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError('Error en la subida del archivo (código ' . (int) $file['error'] . ').');
    }
    if (!preg_match('/\.xlsx$/i', $file['name'])) {
        jsonError('Solo se aceptan archivos .xlsx.');
    }
    if ($file['size'] === 0) {
        jsonError('El archivo está vacío (0 bytes).');
    }

    ini_set('memory_limit', $cfg['limites']['memory']);
    set_time_limit($cfg['limites']['timeout']);

    // Crear sesión: copia original.xlsx y escribe datos.json + estado.json inicial
    $sesionId = $gestor->crear($file['tmp_name'], $file['name']);

    // Segunda lectura para el motor (lee desde la copia de la sesión)
    $lector    = new LectorExcel();
    $datos     = $lector->cargar($gestor->rutaOriginal($sesionId));
    $motor     = construirMotor($cfg);
    $resultado = $motor->validar($datos['atenciones']);

    // Persistir observaciones del sistema en estado.json
    $estado  = $gestor->cargar($sesionId);
    foreach ($resultado->porFila() as $fila => $listaObs) {
        foreach ($listaObs as $obs) {
            $estado['prestaciones'][$obs->pk]['observaciones'][(string) $fila][] = [
                'regla'     => $obs->reglaCodigo,
                'accion'    => $obs->accion,
                'motivo'    => $obs->motivo,
                'color'     => $obs->color,
                'prioridad' => $obs->prioridad,
                'origen'    => 'sistema',
            ];
        }
    }
    $gestor->guardar($sesionId, $estado);

    jsonOk([
        'id'                  => $sesionId,
        'archivo'             => $file['name'],
        'total_prestaciones'  => count($datos['atenciones']),
        'total_observaciones' => $resultado->totalObservaciones(),
        'total_filas_obs'     => count($resultado->resolucionPorFila()),
    ]);
}

// ── DELETE sesion ─────────────────────────────────────────────────────────────

function rutaDeleteSesion(GestorSesiones $gestor): never
{
    $id = req(bodyJson(), 'id');
    $gestor->eliminar($id);
    jsonOk(['eliminada' => $id]);
}

// ── GET sesion?id= ────────────────────────────────────────────────────────────

function rutaGetSesion(GestorSesiones $gestor): never
{
    $id     = req($_GET, 'id');
    $estado = $gestor->cargar($id);

    $validadas = 0;
    $lista     = [];
    $codigos   = $gestor->codigosPorPk($id);

    foreach ($estado['prestaciones'] as $pk => $p) {
        if ($p['validada']) {
            $validadas++;
        }
        $nObs   = 0;
        $reglas = [];
        foreach (($p['observaciones'] ?? []) as $obsFilas) {
            $nObs += count($obsFilas);
            foreach ($obsFilas as $obs) {
                $reglas[$obs['regla']] = true;
            }
        }
        $lista[] = [
            'pk'         => (string) $pk,
            'validada'   => (bool) $p['validada'],
            'ipress_cod' => $p['ipress_cod'] ?? '',
            'ipress_nom' => $p['ipress_nom'] ?? '',
            'n_obs'      => $nObs,
            'n_dx'       => count($p['solicitudes_dx'] ?? []),
            // Para buscar por código CPMS y filtrar por familia de regla
            'codigos'    => $codigos[(string) $pk] ?? [],
            'reglas'     => array_keys($reglas),
        ];
    }

    $total = count($lista);

    jsonOk([
        'id'           => $estado['id'],
        'archivo'      => $estado['archivo'],
        'creada'       => $estado['creada'],
        'total'        => $total,
        'validadas'    => $validadas,
        'progreso'     => $total > 0 ? round($validadas / $total * 100, 1) : 0.0,
        'ipress'       => $estado['ipress'] ?? [],
        'prestaciones' => $lista,
    ]);
}

// ── GET prestacion?id=&pk= ────────────────────────────────────────────────────

function rutaGetPrestacion(GestorSesiones $gestor): never
{
    $id    = req($_GET, 'id');
    $pk    = req($_GET, 'pk');
    $pkStr = (string) $pk;

    $estado = $gestor->cargar($id);

    if (!isset($estado['prestaciones'][$pkStr])) {
        jsonError("PK no encontrado en la sesión: {$pkStr}", 404);
    }

    $p        = $estado['prestaciones'][$pkStr];
    $datosPk  = $gestor->cargarDatosPk($id, $pkStr);
    $obsFilas = is_array($p['observaciones']) ? $p['observaciones'] : [];

    $conObs = [];
    $sinObs = [];

    foreach ($datosPk['filas'] as $fila) {
        $filaStr = (string) $fila['fila'];

        if (!empty($obsFilas[$filaStr])) {
            $obsConIdx = [];
            foreach ($obsFilas[$filaStr] as $idx => $obs) {
                $obsConIdx[] = array_merge(['idx' => $idx], $obs);
            }
            $conObs[] = array_merge($fila, ['observaciones' => $obsConIdx]);
        } else {
            $sinObs[] = $fila;
        }
    }

    jsonOk([
        'pk'           => $pkStr,
        'validada'     => (bool) $p['validada'],
        'ipress_cod'   => $datosPk['ipress_cod'],
        'ipress_nom'   => $datosPk['ipress_nom'],
        'tipo'         => $datosPk['tipo'],
        'fecha_inicio' => $datosPk['fecha_inicio'] ?? '',
        'fecha_fin'    => $datosPk['fecha_fin']    ?? '',
        'diagnosticos' => [
            ['slot' => 1, 'codigo' => $datosPk['diag1_codigo'], 'desc' => $datosPk['diag1_desc']],
            ['slot' => 2, 'codigo' => $datosPk['diag2_codigo'], 'desc' => $datosPk['diag2_desc']],
        ],
        'solicitudes_dx' => array_map(
            static fn(int $i, array $s): array => ['idx' => $i] + $s,
            array_keys($p['solicitudes_dx'] ?? []),
            $p['solicitudes_dx'] ?? []
        ),
        'con_observacion' => $conObs,
        'sin_observacion' => $sinObs,
    ]);
}

// ── POST observacion ──────────────────────────────────────────────────────────

function rutaPostObservacion(GestorSesiones $gestor): never
{
    $body   = bodyJson();
    $id     = req($body, 'id');
    $pk     = req($body, 'pk');
    $fila   = (int) ($body['fila'] ?? 0);
    $accion = req($body, 'accion');
    $motivo = req($body, 'motivo');

    if ($fila <= 0) {
        jsonError('Campo requerido: fila (entero > 0)');
    }

    $estado  = $gestor->cargar($id);
    $pkStr   = (string) $pk;
    $filaStr = (string) $fila;

    if (!isset($estado['prestaciones'][$pkStr])) {
        jsonError("PK no encontrado: {$pkStr}", 404);
    }

    if (!is_array($estado['prestaciones'][$pkStr]['observaciones'])) {
        $estado['prestaciones'][$pkStr]['observaciones'] = [];
    }

    $estado['prestaciones'][$pkStr]['observaciones'][$filaStr][] = [
        'regla'     => 'MANUAL',
        'accion'    => $accion,
        'motivo'    => $motivo,
        'color'     => 'CCCCCC',
        'prioridad' => 0,
        'origen'    => 'manual',
    ];

    $gestor->guardar($id, $estado);

    $idx = count($estado['prestaciones'][$pkStr]['observaciones'][$filaStr]) - 1;
    jsonOk(['fila' => $fila, 'idx' => $idx]);
}

// ── PUT observacion ───────────────────────────────────────────────────────────

function rutaPutObservacion(GestorSesiones $gestor): never
{
    $body    = bodyJson();
    $id      = req($body, 'id');
    $pk      = req($body, 'pk');
    $fila    = (int) ($body['fila'] ?? 0);
    $idx     = isset($body['idx']) ? (int) $body['idx'] : null;

    if ($fila <= 0) {
        jsonError('Campo requerido: fila (entero > 0)');
    }
    if ($idx === null) {
        jsonError('Campo requerido: idx');
    }
    if (!isset($body['accion']) && !isset($body['motivo'])) {
        jsonError('Se requiere al menos accion o motivo para editar.');
    }

    $estado  = $gestor->cargar($id);
    $pkStr   = (string) $pk;
    $filaStr = (string) $fila;

    if (!isset($estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx])) {
        jsonError("Observación no encontrada (fila={$fila}, idx={$idx})", 404);
    }

    $obs = &$estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx];
    if (isset($body['accion'])) {
        $obs['accion'] = (string) $body['accion'];
    }
    if (isset($body['motivo'])) {
        $obs['motivo'] = (string) $body['motivo'];
    }
    $obs['origen'] = 'manual';
    unset($obs);

    $gestor->guardar($id, $estado);
    jsonOk(null);
}

// ── DELETE observacion ────────────────────────────────────────────────────────

function rutaDeleteObservacion(GestorSesiones $gestor): never
{
    $body    = bodyJson();
    $id      = req($body, 'id');
    $pk      = req($body, 'pk');
    $fila    = (int) ($body['fila'] ?? 0);
    $idx     = isset($body['idx']) ? (int) $body['idx'] : null;

    if ($fila <= 0) {
        jsonError('Campo requerido: fila (entero > 0)');
    }
    if ($idx === null) {
        jsonError('Campo requerido: idx');
    }

    $estado  = $gestor->cargar($id);
    $pkStr   = (string) $pk;
    $filaStr = (string) $fila;

    if (!isset($estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx])) {
        jsonError("Observación no encontrada (fila={$fila}, idx={$idx})", 404);
    }

    $lista = $estado['prestaciones'][$pkStr]['observaciones'][$filaStr];
    array_splice($lista, $idx, 1);

    if (empty($lista)) {
        unset($estado['prestaciones'][$pkStr]['observaciones'][$filaStr]);
        if (empty($estado['prestaciones'][$pkStr]['observaciones'])) {
            $estado['prestaciones'][$pkStr]['observaciones'] = new \stdClass();
        }
    } else {
        $estado['prestaciones'][$pkStr]['observaciones'][$filaStr] = array_values($lista);
    }

    $gestor->guardar($id, $estado);
    jsonOk(null);
}

// ── POST eliminar-cpms ────────────────────────────────────────────────────────

function rutaPostEliminarCpms(GestorSesiones $gestor): never
{
    $body   = bodyJson();
    $id     = req($body, 'id');
    $pk     = req($body, 'pk');
    $fila   = (int) ($body['fila'] ?? 0);
    $codigo = req($body, 'codigo');

    if ($fila <= 0) {
        jsonError('Campo requerido: fila (entero > 0)');
    }

    $estado  = $gestor->cargar($id);
    $pkStr   = (string) $pk;
    $filaStr = (string) $fila;

    if (!isset($estado['prestaciones'][$pkStr])) {
        jsonError("PK no encontrado: {$pkStr}", 404);
    }

    if (!is_array($estado['prestaciones'][$pkStr]['observaciones'])) {
        $estado['prestaciones'][$pkStr]['observaciones'] = [];
    }

    $estado['prestaciones'][$pkStr]['observaciones'][$filaStr][] = [
        'regla'     => 'MANUAL',
        'accion'    => 'ELIMINAR',
        'motivo'    => "Eliminación manual del código {$codigo}",
        'color'     => 'CCCCCC',
        'prioridad' => 0,
        'origen'    => 'manual',
    ];

    $gestor->guardar($id, $estado);
    jsonOk(null);
}

// ── POST revisar-obs ──────────────────────────────────────────────────────────

function rutaPostRevisarObs(GestorSesiones $gestor): never
{
    $body    = bodyJson();
    $id      = req($body, 'id');
    $pk      = req($body, 'pk');
    $fila    = (int) ($body['fila'] ?? 0);
    $idx     = isset($body['idx']) ? (int) $body['idx'] : null;

    if ($fila <= 0) jsonError('Campo requerido: fila (entero > 0)');
    if ($idx === null) jsonError('Campo requerido: idx');

    $estado  = $gestor->cargar($id);
    $pkStr   = (string) $pk;
    $filaStr = (string) $fila;

    if (!isset($estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx])) {
        jsonError("Observación no encontrada (fila={$fila}, idx={$idx})", 404);
    }

    $actual  = (bool) ($estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx]['revisada'] ?? false);
    $nuevo   = !$actual;
    $estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx]['revisada'] = $nuevo;
    $gestor->guardar($id, $estado);

    jsonOk(['revisada' => $nuevo]);
}

// ── POST revisar-grupo ────────────────────────────────────────────────────────

function familiaDeReglaPhp(string $regla): string
{
    if (str_starts_with($regla, 'PROHIBIDO')) return 'tipo';
    return match ($regla) {
        'DUPLICADO'   => 'dup',
        'HEMOGRAMA'   => 'hemo',
        'UROCULTIVO'  => 'uro',
        'COAGULACION' => 'coag',
        'SUGERENCIA'  => 'sug',
        default       => 'manual',
    };
}

function rutaPostRevisarGrupo(GestorSesiones $gestor): never
{
    $body   = bodyJson();
    $id     = req($body, 'id');
    $pk     = req($body, 'pk');
    $grupo  = req($body, 'grupo');
    $pkStr  = (string) $pk;

    $estado   = $gestor->cargar($id);
    $obsFilas = $estado['prestaciones'][$pkStr]['observaciones'] ?? [];

    // Recopilar posiciones y determinar target (toggle: si todas revisadas → desmarcar)
    $targets = [];
    $allTrue = true;
    foreach ($obsFilas as $filaStr => $lista) {
        foreach ($lista as $idx => $obs) {
            if (familiaDeReglaPhp((string) ($obs['regla'] ?? '')) === $grupo) {
                $targets[] = [$filaStr, $idx];
                if (!($obs['revisada'] ?? false)) {
                    $allTrue = false;
                }
            }
        }
    }

    $target = !$allTrue;
    foreach ($targets as [$filaStr, $idx]) {
        $estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx]['revisada'] = $target;
    }
    $gestor->guardar($id, $estado);

    jsonOk(['revisada' => $target, 'n' => count($targets)]);
}

// ── POST revisar-varias ───────────────────────────────────────────────────────

/**
 * Fija el estado de revisión de un conjunto explícito de observaciones.
 * Recibe el estado final en vez de alternarlo: si la pantalla y el archivo
 * difieren en alguna, un toggle las dejaría a medias.
 */
function rutaPostRevisarVarias(GestorSesiones $gestor): never
{
    $body  = bodyJson();
    $id    = req($body, 'id');
    $pk    = req($body, 'pk');
    $items = $body['items'] ?? null;

    if (!is_array($items) || $items === []) {
        jsonError('Campo requerido: items (lista de {fila, idx})');
    }
    if (!isset($body['revisada']) || !is_bool($body['revisada'])) {
        jsonError('Campo requerido: revisada (booleano)');
    }

    $estado = $gestor->cargar($id);
    $pkStr  = (string) $pk;

    // Validar todas antes de escribir ninguna, para no dejar el grupo a medias
    $posiciones = [];
    foreach ($items as $it) {
        $filaStr = (string) (int) ($it['fila'] ?? 0);
        $idx     = (int) ($it['idx'] ?? -1);
        if (!isset($estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx])) {
            jsonError("Observación no encontrada (fila={$filaStr}, idx={$idx})", 404);
        }
        $posiciones[] = [$filaStr, $idx];
    }

    foreach ($posiciones as [$filaStr, $idx]) {
        $estado['prestaciones'][$pkStr]['observaciones'][$filaStr][$idx]['revisada'] = $body['revisada'];
    }
    $gestor->guardar($id, $estado);

    jsonOk(['revisada' => $body['revisada'], 'n' => count($posiciones)]);
}

// ── Solicitudes de diagnóstico (CIE-10) ───────────────────────────────────────
//
// Son de la prestación, no de una fila: se guardan aparte de las observaciones
// para que en el Excel no las tape la ACCIÓN de mayor prioridad de la fila, y
// se exportan en su propia columna sobre la primera fila de la prestación.

/** Letra + 2 dígitos + subcategoría opcional (N39, N39.0, N390, S72.001…). */
function normalizarCie10(string $codigo): string
{
    $c = strtoupper(preg_replace('/\s+/', '', $codigo));
    if (!preg_match('/^[A-Z]\d{2}(\.?[0-9A-Z]{1,4})?$/', $c)) {
        jsonError("Código CIE-10 no válido: «{$codigo}». Formato esperado: letra y dos dígitos, p.ej. N39.0");
    }
    return $c;
}

/** POST crea ($idx null) · PUT reemplaza la solicitud $idx. */
function rutaGuardarSolicitudDx(GestorSesiones $gestor, ?int $idx): never
{
    $body   = bodyJson();
    $id     = req($body, 'id');
    $pkStr  = req($body, 'pk');
    $tipo   = req($body, 'tipo');
    $motivo = trim(req($body, 'motivo'));
    $nuevo  = normalizarCie10(req($body, 'nuevo'));

    if (!in_array($tipo, ['AGREGAR', 'CAMBIAR'], true)) {
        jsonError('tipo debe ser AGREGAR o CAMBIAR');
    }

    $estado = $gestor->cargar($id);
    if (!isset($estado['prestaciones'][$pkStr])) {
        jsonError("PK no encontrado: {$pkStr}", 404);
    }
    $lista = $estado['prestaciones'][$pkStr]['solicitudes_dx'] ?? [];
    if ($idx !== null && !isset($lista[$idx])) {
        jsonError("Solicitud no encontrada (idx={$idx})", 404);
    }

    $datosPk = $gestor->cargarDatosPk($id, $pkStr);
    $sol = [
        'tipo'   => $tipo,
        'nuevo'  => $nuevo,
        'desc'   => trim((string) ($body['desc'] ?? '')),
        'motivo' => $motivo,
        // Primera fila de la prestación: ahí la escribe el Excel
        'fila'   => min(array_column($datosPk['filas'], 'fila')),
    ];

    if ($tipo === 'CAMBIAR') {
        // Se guarda el código que se reemplaza para que el Excel diga qué cambia
        $slot   = (int) ($body['slot'] ?? 0);
        $actual = trim((string) ($datosPk["diag{$slot}_codigo"] ?? ''));
        if (!in_array($slot, [1, 2], true) || $actual === '') {
            jsonError('Para modificar hay que elegir un diagnóstico registrado (1 o 2)');
        }
        if (strtoupper($actual) === $nuevo) {
            jsonError("El código nuevo es igual al registrado ({$actual})");
        }
        $sol['slot']   = $slot;
        $sol['actual'] = $actual;
    }

    if ($idx === null) {
        $lista[] = $sol;
    } else {
        $lista[$idx] = $sol;
    }
    $estado['prestaciones'][$pkStr]['solicitudes_dx'] = array_values($lista);
    $gestor->guardar($id, $estado);

    jsonOk(null);
}

function rutaDeleteSolicitudDx(GestorSesiones $gestor): never
{
    $body  = bodyJson();
    $id    = req($body, 'id');
    $pkStr = req($body, 'pk');
    $idx   = (int) ($body['idx'] ?? -1);

    $estado = $gestor->cargar($id);
    if (!isset($estado['prestaciones'][$pkStr]['solicitudes_dx'][$idx])) {
        jsonError("Solicitud no encontrada (idx={$idx})", 404);
    }

    array_splice($estado['prestaciones'][$pkStr]['solicitudes_dx'], $idx, 1);
    $gestor->guardar($id, $estado);

    jsonOk(null);
}

// ── POST validar ──────────────────────────────────────────────────────────────

function rutaPostValidar(GestorSesiones $gestor): never
{
    $body  = bodyJson();
    $id    = req($body, 'id');
    $pk    = req($body, 'pk');
    $pkStr = (string) $pk;

    $estado = $gestor->cargar($id);

    if (!isset($estado['prestaciones'][$pkStr])) {
        jsonError("PK no encontrado: {$pkStr}", 404);
    }

    $estado['prestaciones'][$pkStr]['validada'] = !((bool) $estado['prestaciones'][$pkStr]['validada']);
    $gestor->guardar($id, $estado);

    jsonOk(['validada' => $estado['prestaciones'][$pkStr]['validada']]);
}

// ── POST revalidar ────────────────────────────────────────────────────────────
function rutaPostRevalidar(GestorSesiones $gestor, array $cfg): never
{
    $body = bodyJson();
    $id   = req($body, 'id');

    $ruta_excel = $gestor->rutaOriginal($id);
    if (!file_exists($ruta_excel)) {
        jsonError('No se encontró el archivo original de la sesión.', 404);
    }

    $lector    = new LectorExcel();
    $datos     = $lector->cargar($ruta_excel);
    $motor     = construirMotor($cfg);
    $resultado = $motor->validar($datos['atenciones']);
    unset($datos);

    $total = $gestor->revalidar($id, $resultado);

    jsonOk(['observaciones_sistema' => $total]);
}
