<?php

declare(strict_types=1);

namespace Validador;

use Validador\Reglas\ReglaCodigosDuplicados;
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
            $observaciones = [];
            foreach ($this->reglas as $regla) {
                foreach ($regla->evaluar((string) $pk, $atencion) as $obs) {
                    $observaciones[] = $obs;
                }
            }

            $observaciones = $this->sinConsolidacionesInutiles($observaciones);

            foreach ($this->fusionarAjustesDeCantidad($observaciones) as $obs) {
                $resultado->agregar($obs);
            }
        }

        return $resultado;
    }

    /**
     * Una fila que otra regla ya manda ELIMINAR no necesita además que se
     * consolide su duplicado: decirle al auditor que agrupe cantidades en una
     * fila que va a borrar es una instrucción contradictoria.  Ocurre, por
     * ejemplo, con un código de hemograma que no corresponde a la IPRESS y que
     * además viene repetido dentro de la misma atención.
     *
     * @param  list<Observacion> $observaciones
     * @return list<Observacion>
     */
    private function sinConsolidacionesInutiles(array $observaciones): array
    {
        $filasEliminadas = [];
        foreach ($observaciones as $obs) {
            if ($obs->reglaCodigo !== ReglaCodigosDuplicados::CODIGO && $obs->accion === 'ELIMINAR') {
                $filasEliminadas[$obs->fila] = true;
            }
        }

        if ($filasEliminadas === []) {
            return $observaciones;
        }

        return array_values(array_filter(
            $observaciones,
            static fn(Observacion $obs): bool =>
                $obs->reglaCodigo !== ReglaCodigosDuplicados::CODIGO
                || !isset($filasEliminadas[$obs->fila])
        ));
    }

    /**
     * Cuando un código válido de hemograma viene repetido, consolidar el
     * duplicado y cuadrar la cantidad con su código par son el mismo trabajo:
     * la fila aparecería dos veces pidiendo lo mismo.  Se fusionan en la
     * observación de duplicados para dejar una sola entrada.
     *
     * @param  list<Observacion> $observaciones
     * @return list<Observacion>
     */
    private function fusionarAjustesDeCantidad(array $observaciones): array
    {
        $consolidacion = [];
        foreach ($observaciones as $i => $obs) {
            if ($obs->reglaCodigo === ReglaCodigosDuplicados::CODIGO
                && str_starts_with($obs->accion, 'AGREGAR')) {
                $consolidacion[$obs->fila] = $i;
            }
        }

        $aFusionar = [];
        foreach ($observaciones as $i => $obs) {
            if (str_starts_with($obs->accion, 'IGUALAR') && isset($consolidacion[$obs->fila])) {
                $aFusionar[$i] = $consolidacion[$obs->fila];
            }
        }

        if ($aFusionar === []) {
            return $observaciones;
        }

        foreach ($aFusionar as $i => $j) {
            $dup = $observaciones[$j];
            $ig  = $observaciones[$i];

            $observaciones[$j] = new Observacion(
                fila:        $dup->fila,
                pk:          $dup->pk,
                codigo:      $dup->codigo,
                valor:       $dup->valor,
                reglaCodigo: $dup->reglaCodigo,
                reglaNombre: $dup->reglaNombre,
                prioridad:   $dup->prioridad,
                color:       $dup->color,
                motivo:      $dup->motivo . ' || ' . $ig->motivo,
                accion:      $dup->accion . ' - ' . $ig->accion,
            );
        }

        return array_values(array_filter(
            $observaciones,
            static fn(int $i): bool => !isset($aFusionar[$i]),
            ARRAY_FILTER_USE_KEY
        ));
    }

    /** Lista de reglas registradas (útil para construir la leyenda en la UI). */
    public function reglas(): array
    {
        return $this->reglas;
    }
}
