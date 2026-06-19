<?php

declare(strict_types=1);

return [

    // ── Libro Excel ────────────────────────────────────────────────────────
    'hoja' => 'DATA',

    // Encabezados exactos que se buscan en el archivo (tolerante a acentos)
    'columnas' => [
        'pk'     => 'PK',
        'codigo' => 'COD. CPMS',
        'tipo'   => 'TIPO DE ATENCIÓN',
        'desc'   => 'DESCRIPCION CPMS',
        'valor'  => 'valor',
    ],

    // ── Grupos de códigos para ReglaRedundanciaGrupo (Fase 3) ─────────────
    'grupos' => [
        'hemograma' => [
            'nombre'  => 'Hemograma',
            'codigos' => ['85025', '85027', '85007', '85013', '85014',
                          '85018', '85032', '85049', '85590'],
            'color'   => 'FFE599', // ámbar claro
        ],
        'urocultivo' => [
            'nombre'  => 'Urocultivo',
            'codigos' => ['87086', '87087', '87088'],
            'color'   => 'B7E1E4', // turquesa claro
        ],
    ],

    // ── Colores de fila por tipo de acción (hex RGB sin #) ────────────────
    // Precedencia (mayor = más prioritario):
    //   rojo(4) > violeta(3) > ámbar(2) > turquesa(1)
    'colores' => [
        'ELIMINAR_PROHIBIDO'  => ['hex' => 'FFCCCC', 'prioridad' => 4], // rojo
        'ELIMINAR_DUPLICADO'  => ['hex' => 'E8CCFF', 'prioridad' => 3], // violeta
        'REVISAR_HEMOGRAMA'   => ['hex' => 'FFE599', 'prioridad' => 2], // ámbar
        'REVISAR_UROCULTIVO'  => ['hex' => 'B7E1E4', 'prioridad' => 1], // turquesa
    ],

    // ── Rendimiento ────────────────────────────────────────────────────────
    'limites' => [
        'memory'          => '1G',
        'timeout'         => 120,       // segundos
        'max_filas_tabla' => 5000,      // filas mostradas en pantalla (Fase 5)
    ],

    // ── Storage ────────────────────────────────────────────────────────────
    'storage_dir' => __DIR__ . '/../storage',
    'token_ttl'   => 3600, // segundos antes de limpiar archivos viejos

];
