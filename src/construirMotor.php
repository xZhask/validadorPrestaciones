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

    foreach ([
        ['PROHIBIDO_93784', 'Código 93784 no permitido (tipo 2 y 3)', '93784', ['2', '3']],
        ['PROHIBIDO_99246', 'Código 99246 no permitido (tipo 2 y 3)', '99246', ['2', '3']],
        ['PROHIBIDO_15000', 'Código 15000 no permitido (trasplantes, Nivel II)', '15000', []],
    ] as [$rc, $rn, $cod, $tipos]) {
        $m->registrar(new ReglaCodigoNoPermitidoPorTipo(
            codigoRegla:     $rc,
            nombreRegla:     $rn,
            colorHex:        $cfg['colores']['ELIMINAR_PROHIBIDO']['hex'],
            prioridadVal:    $cfg['colores']['ELIMINAR_PROHIBIDO']['prioridad'],
            codigoCpms:      $cod,
            tiposProhibidos: $tipos,
            accionTexto:     'ELIMINAR',
        ));
    }

    $m->registrar(new ReglaCodigosDuplicados());

    $m->registrar(new ReglaHemograma(
        colorElim:      $cfg['grupos']['hemograma']['color'],
        prioridadElim:  $cfg['colores']['ELIMINAR_HEMOGRAMA']['prioridad'],
        colorSug:       $cfg['colores']['SUGERENCIA']['hex'],
        prioridadSug:   $cfg['colores']['SUGERENCIA']['prioridad'],
        codigos:        $cfg['grupos']['hemograma']['codigos'],
        ipressMapeo:    $cfg['grupos']['hemograma']['ipress'],
    ));

    $m->registrar(new ReglaRedundanciaGrupo(
        codigoRegla:  'UROCULTIVO',
        nombreRegla:  'Redundancia Urocultivo',
        colorHex:     $cfg['grupos']['urocultivo']['color'],
        prioridadVal: $cfg['colores']['ELIMINAR_UROCULTIVO']['prioridad'],
        codigos:      $cfg['grupos']['urocultivo']['codigos'],
    ));

    return $m;
}
