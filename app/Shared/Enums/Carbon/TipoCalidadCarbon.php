<?php

namespace App\Shared\Enums\Carbon;

/**
 * Calidades del carbón según su porcentaje de ceniza.
 * Los rangos (inicio/fin) NO son fijos: viven en la tabla `calidad_carbon`
 * y pueden ser ajustados desde el módulo System. Este enum solo define
 * las tres calidades soportadas.
 *
 * OJO: la tabla `calidad_carbon` puede tener mas filas que este enum (por
 * ejemplo `Calidad`, para el carbon con mas de 30% de ceniza). Cuando la
 * validacion debe aceptar el catalogo completo, hay que leer `calidad_carbon`
 * y no este enum.
 *
 * Los valores deben coincidir con `calidad_carbon.nombre`, que es lo que
 * se graba en `carga_compra_carbon.calidad`, `stock_carbon.calidad`,
 * `kardex_carbon.calidad` y `zarandeo_carbon.calidad`.
 */
enum TipoCalidadCarbon: string
{
    case Baja = 'Baja';
    case Intermedia = 'Intermedia';
    case Alta = 'Alta';
}
