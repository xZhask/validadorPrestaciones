<?php

declare(strict_types=1);

namespace Validador;

/**
 * Normalización de cadenas para comparación de encabezados de columna.
 * Tolerante a: mayúsculas/minúsculas, acentos, espacios múltiples.
 */
class Texto
{
    private static array $mapa = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'Ä' => 'a', 'Ë' => 'e', 'Ï' => 'i', 'Ö' => 'o', 'Ü' => 'u',
        'ñ' => 'n', 'Ñ' => 'n',
    ];

    /**
     * Devuelve la clave canónica de un encabezado:
     * minúsculas, sin acentos, espacios internos colapsados a uno, sin bordes.
     */
    public static function clave(string $texto): string
    {
        $texto = strtr($texto, self::$mapa);
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        return (string) preg_replace('/\s+/', ' ', $texto);
    }
}
