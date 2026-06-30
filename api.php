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
        'GET:prestacion'      => rutaGetPrestacion($gestor),
        'POST:observacion'    => rutaPostObservacion($gestor),
        'PUT:observacion'     => rutaPutObservacion($gestor),
        'DELETE:observacion'  => rutaDeleteObservacion($gestor),
        'POST:eliminar-cpms'  => rutaPostEliminarCpms($gestor),
        'POST:validar'        => rutaPostValidar($gestor),
        'POST:revisar-obs'    => rutaPostRevisarObs($gestor),
        'POST:revisar-grupo'  => rutaPostRevisarGrupo($gestor),
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

// ── GET sesion?id= ────────────────────────────────────────────────────────────

function rutaGetSesion(GestorSesiones $gestor): never
{
    $id     = req($_GET, 'id');
    $estado = $gestor->cargar($id);

    $validadas = 0;
    $lista     = [];

    foreach ($estado['prestaciones'] as $pk => $p) {
        if ($p['validada']) {
            $validadas++;
        }
        $nObs = 0;
        foreach (($p['observaciones'] ?? []) as $obsFilas) {
            $nObs += count($obsFilas);
        }
        $lista[] = [
            'pk'         => (string) $pk,
            'validada'   => (bool) $p['validada'],
            'ipress_cod' => $p['ipress_cod'] ?? '',
            'ipress_nom' => $p['ipress_nom'] ?? '',
            'n_obs'      => $nObs,
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
        'pk'          => $pkStr,
        'validada'    => (bool) $p['validada'],
        'ipress_cod'  => $datosPk['ipress_cod'],
        'ipress_nom'  => $datosPk['ipress_nom'],
        'tipo'        => $datosPk['tipo'],
        'diagnosticos' => [
            ['slot' => 1, 'codigo' => $datosPk['diag1_codigo'], 'desc' => $datosPk['diag1_desc']],
            ['slot' => 2, 'codigo' => $datosPk['diag2_codigo'], 'desc' => $datosPk['diag2_desc']],
        ],
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
        'DUPLICADO'  => 'dup',
        'HEMOGRAMA'  => 'hemo',
        'UROCULTIVO' => 'uro',
        'SUGERENCIA' => 'sug',
        default      => 'manual',
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
