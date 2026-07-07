<?php

declare(strict_types=1);

namespace Validador;

class GestorSesiones
{
    private string $dir;
    private const ID_REGEX = '/^[0-9a-f]{32}$/';

    public function __construct(string $storageDir)
    {
        $this->dir = rtrim($storageDir, '/\\') . DIRECTORY_SEPARATOR . 'sesiones';
    }

    /**
     * Crea una nueva sesión: copia el Excel, lee PKs e IPRESS, escribe estado.json inicial.
     * Devuelve el ID de la sesión (32 hex chars).
     */
    public function crear(string $rutaSubida, string $nombreOriginal): string
    {
        $id      = bin2hex(random_bytes(16));
        $carpeta = $this->carpeta($id);

        if (!mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
            throw new \RuntimeException("No se pudo crear la carpeta de sesión: {$carpeta}");
        }

        $destino = $carpeta . DIRECTORY_SEPARATOR . 'original.xlsx';
        if (!copy($rutaSubida, $destino)) {
            throw new \RuntimeException("No se pudo copiar el archivo a la sesión.");
        }

        $lector = new LectorExcel();
        $datos  = $lector->cargar($destino);

        $prestaciones = [];
        $datosPorPk   = [];

        foreach ($datos['atenciones'] as $pk => $filas) {
            $pkStr   = (string) $pk;
            $primera = $filas[0];

            $prestaciones[$pkStr] = [
                'validada'      => false,
                'ipress_cod'    => $primera['ipress_cod'] ?? '',
                'ipress_nom'    => $primera['ipress_nom'] ?? '',
                'observaciones' => (object) [],
            ];

            $datosPorPk[$pkStr] = [
                'ipress_cod'   => $primera['ipress_cod']   ?? '',
                'ipress_nom'   => $primera['ipress_nom']   ?? '',
                'tipo'         => $primera['tipo']         ?? '',
                'fecha_inicio' => $primera['fecha_inicio'] ?? '',
                'fecha_fin'    => $primera['fecha_fin']    ?? '',
                'diag1_codigo' => $primera['diag1_codigo'] ?? '',
                'diag1_desc'   => $primera['diag1_desc']   ?? '',
                'diag2_codigo' => $primera['diag2_codigo'] ?? '',
                'diag2_desc'   => $primera['diag2_desc']   ?? '',
                'filas'        => array_map(static fn(array $f): array => [
                    'fila'     => $f['fila'],
                    'codigo'   => $f['codigo'],
                    'desc'     => $f['desc'],
                    'cantidad' => $f['cantidad'],
                    'valor'    => $f['valor'],
                ], $filas),
            ];
        }

        // datos.json: caché de solo lectura para los endpoints de detalle
        $rutaDatos = $carpeta . DIRECTORY_SEPARATOR . 'datos.json';
        $tmpDatos  = $rutaDatos . '.tmp';
        $jsonDatos = json_encode($datosPorPk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($tmpDatos, $jsonDatos, LOCK_EX);
        rename($tmpDatos, $rutaDatos);
        unset($datosPorPk, $jsonDatos);

        $estado = [
            'id'                 => $id,
            'archivo'            => $nombreOriginal,
            'creada'             => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'hoja'               => 'DATA',
            'total_prestaciones' => count($prestaciones),
            'ipress'             => $datos['ipress'] ?? [],
            'prestaciones'       => $prestaciones,
        ];

        $this->guardar($id, $estado);

        return $id;
    }

    /**
     * Lista todas las sesiones con sus metadatos y progreso.
     */
    public function listar(): array
    {
        $sesiones = [];
        if (!is_dir($this->dir)) {
            return $sesiones;
        }

        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'estado.json') as $archivo) {
            try {
                $estado    = $this->leerJson($archivo);
                $total     = $estado['total_prestaciones'] ?? 0;
                $validadas = $this->contarValidadas($estado);

                $sesiones[] = [
                    'id'        => $estado['id'],
                    'archivo'   => $estado['archivo'],
                    'creada'    => $estado['creada'],
                    'total'     => $total,
                    'validadas' => $validadas,
                    'progreso'  => $total > 0 ? round($validadas / $total * 100, 1) : 0.0,
                    'ipress'    => $estado['ipress'] ?? [],
                ];
            } catch (\Throwable) {
                // sesión corrupta — se omite sin interrumpir el listado
            }
        }

        usort($sesiones, fn (array $a, array $b): int => strcmp($b['creada'], $a['creada']));

        return $sesiones;
    }

    /**
     * Carga y retorna el estado.json de la sesión como array.
     */
    public function cargar(string $id): array
    {
        $this->validarId($id);
        return $this->leerJson($this->carpeta($id) . DIRECTORY_SEPARATOR . 'estado.json');
    }

    /**
     * Persiste el estado.json de forma atómica (temp + rename).
     */
    public function guardar(string $id, array $estado): void
    {
        $this->validarId($id);
        $ruta = $this->carpeta($id) . DIRECTORY_SEPARATOR . 'estado.json';
        $tmp  = $ruta . '.tmp';

        $json = json_encode(
            $estado,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException('Error serializando el estado de la sesión a JSON.');
        }

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException("No se pudo escribir el estado temporal de la sesión.");
        }

        if (!rename($tmp, $ruta)) {
            @unlink($tmp);
            throw new \RuntimeException("No se pudo guardar el estado de la sesión (rename fallido).");
        }
    }

    /**
     * Devuelve la ruta absoluta al original.xlsx de la sesión.
     */
    public function rutaOriginal(string $id): string
    {
        $this->validarId($id);
        return $this->carpeta($id) . DIRECTORY_SEPARATOR . 'original.xlsx';
    }

    /**
     * Devuelve los datos de una prestación desde datos.json (sin releer el Excel).
     * Requiere que la sesión haya sido creada con la versión que escribe datos.json.
     *
     * @throws \RuntimeException si datos.json no existe o el PK no está
     */
    public function cargarDatosPk(string $id, string $pk): array
    {
        $this->validarId($id);
        $ruta = $this->carpeta($id) . DIRECTORY_SEPARATOR . 'datos.json';

        if (!file_exists($ruta)) {
            throw new \RuntimeException(
                'datos.json no encontrado. Crea una nueva sesión para acceder al detalle de prestaciones.'
            );
        }

        $todos = $this->leerJson($ruta);

        if (!isset($todos[$pk])) {
            throw new \RuntimeException("PK no encontrado en datos.json: {$pk}");
        }

        return $todos[$pk];
    }

    /**
     * Re-ejecuta las observaciones de sistema sobre la sesión existente,
     * conservando las observaciones manuales y el flag validada de cada prestación.
     *
     * @return int Número de nuevas observaciones de sistema insertadas
     */
    public function revalidar(string $id, ResultadoValidacion $resultado): int
    {
        $this->validarId($id);
        $estado  = $this->cargar($id);
        $porFila = $resultado->porFila();

        // Capturar qué observaciones de sistema estaban marcadas como revisadas
        // antes de limpiarlas, para restaurar ese estado al reinsertarlas.
        $revisadasPrevias = [];
        foreach ($estado['prestaciones'] as $pk => $prestacion) {
            foreach ($prestacion['observaciones'] ?? [] as $fila => $lista) {
                foreach ($lista as $obs) {
                    if (($obs['origen'] ?? '') === 'sistema' && !empty($obs['revisada'])) {
                        $revisadasPrevias[$pk][(string) $fila][$obs['regla']] = true;
                    }
                }
            }
        }

        // Conservar solo observaciones manuales en cada prestación
        foreach ($estado['prestaciones'] as $pk => &$prestacion) {
            $soloManuales = [];
            foreach ($prestacion['observaciones'] ?? [] as $fila => $lista) {
                $filtradas = array_values(
                    array_filter($lista, static fn(array $o): bool => ($o['origen'] ?? '') === 'manual')
                );
                if ($filtradas !== []) {
                    $soloManuales[(string) $fila] = $filtradas;
                }
            }
            $prestacion['observaciones'] = $soloManuales;
        }
        unset($prestacion);

        // Insertar nuevas observaciones de sistema,
        // pero saltar si el usuario ya editó manualmente esa regla en esa fila.
        $total = 0;
        foreach ($porFila as $fila => $listaObs) {
            foreach ($listaObs as $obs) {
                $filaStr = (string) $fila;
                $pkStr   = $obs->pk;

                $existentes = $estado['prestaciones'][$pkStr]['observaciones'][$filaStr] ?? [];
                foreach ($existentes as $ex) {
                    if (($ex['origen'] ?? '') === 'manual' && ($ex['regla'] ?? '') === $obs->reglaCodigo) {
                        continue 2;
                    }
                }

                $estado['prestaciones'][$pkStr]['observaciones'][$filaStr][] = [
                    'regla'     => $obs->reglaCodigo,
                    'accion'    => $obs->accion,
                    'motivo'    => $obs->motivo,
                    'color'     => $obs->color,
                    'prioridad' => $obs->prioridad,
                    'origen'    => 'sistema',
                    'revisada'  => $revisadasPrevias[$pkStr][$filaStr][$obs->reglaCodigo] ?? false,
                ];
                $total++;
            }
        }

        $this->guardar($id, $estado);
        return $total;
    }

    /**
     * Devuelve la primera prestación no validada, o null si todas están validadas.
     */
    public function primeraPendiente(array $estado): ?string
    {
        foreach ($estado['prestaciones'] as $pk => $p) {
            if ($p['validada'] === false) {
                return (string) $pk;
            }
        }
        return null;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function carpeta(string $id): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $id;
    }

    private function validarId(string $id): void
    {
        if (!preg_match(self::ID_REGEX, $id)) {
            throw new \InvalidArgumentException("ID de sesión inválido.");
        }
    }

    private function leerJson(string $ruta): array
    {
        if (!file_exists($ruta)) {
            throw new \RuntimeException("No existe el estado de sesión: {$ruta}");
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new \RuntimeException("No se pudo leer el estado de sesión: {$ruta}");
        }
        $datos = json_decode($contenido, true);
        if (!is_array($datos)) {
            throw new \RuntimeException("Estado de sesión corrupto: {$ruta}");
        }
        return $datos;
    }

    private function contarValidadas(array $estado): int
    {
        $count = 0;
        foreach ($estado['prestaciones'] ?? [] as $p) {
            if (($p['validada'] ?? false) === true) {
                $count++;
            }
        }
        return $count;
    }
}
