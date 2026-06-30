<?php

declare(strict_types=1);

namespace Validador;

/**
 * Normaliza COD. CPMS resolviendo el error de precisión flotante de Excel.
 *
 * Ejemplo: 99207.039999999994  →  "99207.04"
 *          99207.030000000001  →  "99207.03"
 *          93005.0             →  "93005"
 *
 * Estrategia: formatear a 6 decimales (absorbe el ruido IEEE-754 de Excel)
 * y luego recortar ceros finales.  6 decimales es suficiente porque el CPMS
 * usa hasta 2 decimales reales; el ruido aparece a partir del 3.er decimal.
 */
class Normalizador
{
    public static function codigo(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        if (is_numeric($raw)) {
            $s = number_format((float) $raw, 6, '.', '');
            return rtrim(rtrim($s, '0'), '.');
        }
        return trim((string) $raw);
    }
}
