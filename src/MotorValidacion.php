<?php

declare(strict_types=1);

namespace Validador;

use Validador\Reglas\ReglaInterface;

/**
 * Orquestador del motor de reglas.
 *
 * Uso:
 *   $motor = new MotorValidacion();
 *   $motor->registrar(new ReglaCodigosDuplicados());
 *   $motor->registrar(new ReglaRedundanciaGrupo(...));
 *   $resultado = $motor->validar($datos['atenciones']);
 */
class MotorValidacion
{
    /** @var list<ReglaInterface> */
    private array $reglas = [];

    /**
     * Registra una regla en el motor.
     * El orden de registro no afecta al resultado; la prioridad se resuelve
     * en ResultadoValidacion por el campo prioridad() de cada regla.
     */
    public function registrar(ReglaInterface $regla): void
    {
        $this->reglas[] = $regla;
    }

    /**
     * Ejecuta todas las reglas registradas sobre cada atención.
     *
     * @param array<string, list<array{fila:int,codigo:string,tipo:string,desc:string,valor:float|null}>> $atenciones
     *        Tal como lo devuelve LectorExcel::cargar()['atenciones'].
     */
    public function validar(array $atenciones): ResultadoValidacion
    {
        $resultado = new ResultadoValidacion();

        foreach ($atenciones as $pk => $atencion) {
            foreach ($this->reglas as $regla) {
                foreach ($regla->evaluar((string) $pk, $atencion) as $obs) {
                    $resultado->agregar($obs);
                }
            }
        }

        return $resultado;
    }

    /** Lista de reglas registradas (útil para construir la leyenda en la UI). */
    public function reglas(): array
    {
        return $this->reglas;
    }
}
