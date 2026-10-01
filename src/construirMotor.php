<?php

declare(strict_types=1);

use Validador\MotorValidacion;
use Validador\Reglas\ReglaCodigosDuplicados;
use Validador\Reglas\ReglaCodigoNoPermitidoPorTipo;
use Validador\Reglas\ReglaHemograma;
use Validador\Reglas\ReglaRedundanciaGrupo;

function construirMotor(array $cfg): MotorValidacion
{
    $m = new MotorValidacion();

    foreach ($cfg['prohibidos'] as $cod => $p) {
        // Las claves numéricas de un array PHP llegan como int ("93784" → 93784)
        $cod = (string) $cod;
        $m->registrar(new ReglaCodigoNoPermitidoPorTipo(
            codigoRegla:     "PROHIBIDO_{$cod}",
            nombreRegla:     "Código {$cod} no permitido ({$p['nota']})",
            colorHex:        $cfg['colores']['ELIMINAR_PROHIBIDO']['hex'],
            prioridadVal:    $cfg['colores']['ELIMINAR_PROHIBIDO']['prioridad'],
            codigoCpms:      $cod,
            tiposProhibidos: $p['tipos'],
            accionTexto:     'ELIMINAR',
        ));
    }

    $m->registrar(new ReglaCodigosDuplicados(
        colorHex:     $cfg['colores']['ELIMINAR_DUPLICADO']['hex'],
        prioridadVal: $cfg['colores']['ELIMINAR_DUPLICADO']['prioridad'],
    ));

    $m->registrar(new ReglaHemograma(
        colorElim:      $cfg['grupos']['hemograma']['color'],
        prioridadElim:  $cfg['colores']['ELIMINAR_HEMOGRAMA']['prioridad'],
        colorSug:       $cfg['colores']['SUGERENCIA']['hex'],
        prioridadSug:   $cfg['colores']['SUGERENCIA']['prioridad'],
        codigos:        $cfg['grupos']['hemograma']['codigos'],
        ipressPorCodigo: $cfg['grupos']['hemograma']['ipress_codigos'],
        ipressPorNombre: $cfg['grupos']['hemograma']['ipress_nombres'],
    ));

    $m->registrar(new ReglaRedundanciaGrupo(
        codigoRegla:  'UROCULTIVO',
        nombreRegla:  'Redundancia Urocultivo',
        colorHex:     $cfg['grupos']['urocultivo']['color'],
        prioridadVal: $cfg['colores']['ELIMINAR_UROCULTIVO']['prioridad'],
        codigos:      $cfg['grupos']['urocultivo']['codigos'],
    ));

    $m->registrar(new ReglaRedundanciaGrupo(
        codigoRegla:      'COAGULACION',
        nombreRegla:      'Redundancia Tiempo de coagulación',
        colorHex:         $cfg['grupos']['coagulacion']['color'],
        prioridadVal:     $cfg['colores']['ELIMINAR_COAGULACION']['prioridad'],
        codigos:          $cfg['grupos']['coagulacion']['codigos'],
        codigoPreferente: $cfg['grupos']['coagulacion']['preferente'],
    ));

    return $m;
}
