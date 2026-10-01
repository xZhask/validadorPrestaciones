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
            'codigos' => ['85004', '85025', '85027', '85007', '85013', '85014',
                          '85018', '85032', '85049', '85590'],
            'color'   => 'FFE599', // ámbar claro (para obs. ELIMINAR)
            // Representación válida por IPRESS. Se resuelve primero por CÓDIGO
            // IPRESS (RENIPRESS), que es exacto e inmune a cómo venga escrito el
            // nombre; los ceros a la izquierda se ignoran, porque Excel los pierde
            // cuando guarda la celda como número.
            'ipress_codigos' => [
                '00011794' => ['85027', '85007'], // H. Regional PNP Arequipa
                '00011833' => ['85027', '85007'], // H. Regional Policial Chiclayo
                '00016094' => ['85025'],          // H. PNP "Augusto B. Leguía"
                '00014718' => ['85025'],          // H. Policial Geriátrico San José
            ],
            // Respaldo si el archivo no trae el código: patrón buscado DENTRO del
            // nombre normalizado, porque los nombres reales son largos.
            'ipress_nombres' => [
                'arequipa'   => ['85027', '85007'],
                'chiclayo'   => ['85027', '85007'],
                'legu'       => ['85025'], // Augusto B. Leguía
                'geriatrico' => ['85025'],
                'san jos'    => ['85025'], // Geriátrico San José
            ],
        ],
        'urocultivo' => [
            'nombre'  => 'Urocultivo',
            'codigos' => ['87086', '87087', '87088'],
            'color'   => 'B7E1E4', // turquesa claro
        ],
        'coagulacion' => [
            'nombre'     => 'Coagulación',
            'codigos'    => ['85345', '85347', '85348'],
            // A diferencia de urocultivo, aquí no se conserva el de mayor valor
            // sino siempre el 85345 (Lee y White) cuando está presente.
            'preferente' => '85345',
            'color'      => 'F4CCE4', // rosa claro
        ],
    ],

    // ── Códigos no permitidos ──────────────────────────────────────────────
    // codigo CPMS => tipos de atención en que se prohíbe ([] = en cualquiera)
    // y la nota que completa el nombre de la regla:
    //   "Código <codigo> no permitido (<nota>)"
    'prohibidos' => [
        '93784'    => ['tipos' => ['2', '3'], 'nota' => 'tipo 2 y 3'],
        '99246'    => ['tipos' => ['2', '3'], 'nota' => 'tipo 2 y 3'],
        '99246.01' => ['tipos' => ['2', '3'], 'nota' => 'tipo 2 y 3'],
        '99246.02' => ['tipos' => ['2', '3'], 'nota' => 'tipo 2 y 3'],
        '15000'    => ['tipos' => [],         'nota' => 'trasplantes, Nivel II'],
    ],

    // ── Colores de fila por tipo de regla (hex RGB sin #) ─────────────────
    // Precedencia (mayor = más prioritario):
    //   tipo(5) > dup(4) > hemo-elim(3) > uro/coag(2) > sug(1) > manual(0)
    // uro y coag comparten prioridad: sus códigos son disjuntos, así que una
    // misma fila nunca puede caer en ambas reglas.
    'colores' => [
        'ELIMINAR_PROHIBIDO'   => ['hex' => 'FFCCCC', 'prioridad' => 5], // rojo
        'ELIMINAR_DUPLICADO'   => ['hex' => 'E8CCFF', 'prioridad' => 4], // violeta
        'ELIMINAR_HEMOGRAMA'   => ['hex' => 'FFE599', 'prioridad' => 3], // ámbar
        'ELIMINAR_UROCULTIVO'  => ['hex' => 'B7E1E4', 'prioridad' => 2], // turquesa
        'ELIMINAR_COAGULACION' => ['hex' => 'F4CCE4', 'prioridad' => 2], // rosa
        'SUGERENCIA'           => ['hex' => '93C5FD', 'prioridad' => 1], // azul claro
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
