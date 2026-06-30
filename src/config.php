<?php

declare(strict_types=1);

date_default_timezone_set('America/Lima');

return [

    // ── Libro Excel ────────────────────────────────────────────────────────
    'hoja' => 'DATA',

    // Encabezados exactos que se buscan en el archivo (tolerante a acentos)
    'columnas' => [
        'pk'            => 'PK',
        'codigo'        => 'COD. CPMS',
        'tipo'          => 'TIPO DE ATENCIÓN',
        'desc'          => 'DESCRIPCION CPMS',
        'valor'         => 'valor',
        'cantidad'      => 'cantidad',
        'ipress_codigo' => 'CÓDIGO IPRESS',
        'ipress_nombre' => 'NOMBRE IPRESS',
        'fecha_inicio'  => 'FECHA DE ATENCIÓN',
        'fecha_fin'     => 'FECHA DE ALTA',
        'diag1_codigo'  => 'CODIGO DEL DIAGNOSTICO1',
        'diag1_desc'    => 'DESCRIPCION DEL DIAGNOSTICO1',
        'diag2_codigo'  => 'CODIGO DEL DIAGNOSTICO2',
        'diag2_desc'    => 'DESCRIPCION DEL DIAGNOSTICO2',
    ],

    // ── Grupos de códigos por regla ────────────────────────────────────────
    'grupos' => [
        'hemograma' => [
            'nombre'  => 'Hemograma',
            'codigos' => ['85025', '85027', '85007', '85013', '85014',
                          '85018', '85032', '85049', '85590'],
            'color'   => 'FFE599', // ámbar claro (para obs. ELIMINAR)
            // Representación válida por IPRESS (clave = nombre normalizado vía Texto::clave())
            'ipress'  => [
                'arequipa'   => ['85027', '85007'],
                'chiclayo'   => ['85027', '85007'],
                'abl'        => ['85025'],
                'geriatrico' => ['85025'],
            ],
        ],
        'urocultivo' => [
            'nombre'  => 'Urocultivo',
            'codigos' => ['87086', '87087', '87088'],
            'color'   => 'B7E1E4', // turquesa claro
        ],
    ],

    // ── Colores de fila por tipo de regla (hex RGB sin #) ─────────────────
    // Precedencia (mayor = más prioritario):
    //   tipo(5) > dup(4) > hemo-elim(3) > uro(2) > sug(1) > manual(0)
    'colores' => [
        'ELIMINAR_PROHIBIDO'  => ['hex' => 'FFCCCC', 'prioridad' => 5], // rojo
        'ELIMINAR_DUPLICADO'  => ['hex' => 'E8CCFF', 'prioridad' => 4], // violeta
        'ELIMINAR_HEMOGRAMA'  => ['hex' => 'FFE599', 'prioridad' => 3], // ámbar
        'ELIMINAR_UROCULTIVO' => ['hex' => 'B7E1E4', 'prioridad' => 2], // turquesa
        'SUGERENCIA'          => ['hex' => '93C5FD', 'prioridad' => 1], // azul claro
    ],

    // ── Rendimiento ────────────────────────────────────────────────────────
    'limites' => [
        'memory'          => '1G',
        'timeout'         => 120,       // segundos
        'max_filas_tabla' => 5000,      // filas mostradas en pantalla
    ],

    // ── Storage ────────────────────────────────────────────────────────────
    'storage_dir' => __DIR__ . '/../storage',
    'token_ttl'   => 3600, // segundos antes de limpiar archivos viejos

];
