<?php

namespace App\Services;

use App\Data\StockCarbonData;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class StockCarbonService
{
    /**
     * Stock de carbon disponible por almacen, tipo y calidad.
     */
    public static function get_stocks(array $opts = [])
    {
        // La matriz de stock se completa antes de listar: si falta alguna
        // combinacion (almacen x tipo x calidad) se inserta con stock 0.
        self::asegurar_filas_stock();

        return ApiResponse::success(StockCarbonData::get_stocks($opts));
    }

    /**
     * Completa la matriz de stock de carbon: por cada almacen de carbon y
     * cada tipo de carbon deben existir las tres calidades (Baja, Intermedia,
     * Alta), aunque su stock sea 0.
     *
     * Es idempotente: solo inserta las filas que faltan y no toca las
     * existentes. No genera kardex porque no hay movimiento fisico, la fila
     * solo representa que aun no hay carbon de esa combinacion.
     *
     * @return int cantidad de filas insertadas
     */
    public static function asegurar_filas_stock(): int
    {
        // Chequeo barato sin bloqueos: si la matriz ya esta completa no se
        // abre transaccion ni se escribe nada.
        if (empty(StockCarbonData::detectar_faltantes())) {
            return 0;
        }

        return DB::transaction(function () {
            // Se bloquean las filas padre (tipos y almacenes) para que dos
            // peticiones simultaneas no inserten la misma combinacion.
            StockCarbonData::bloquear_padres();

            $faltantes = StockCarbonData::detectar_faltantes();
            if (empty($faltantes)) {
                return 0;
            }

            StockCarbonData::insertar_filas($faltantes);

            return count($faltantes);
        });
    }
}
