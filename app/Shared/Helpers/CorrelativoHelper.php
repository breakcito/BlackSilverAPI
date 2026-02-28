<?php

namespace App\Shared\Helpers;

use App\Shared\Enums\Periodo;
use Illuminate\Support\Facades\DB;

class CorrelativoHelper
{
    /**
     * Genera el siguiente correlativo para una tabla.
     *
     * Las tablas deben tener 2 columnas:
     * correlativo (VARCHAR): código formateado según el periodo de reseteo
     * numero_correlativo (INT): número secuencial autoincremental dentro del contexto filtrado
     *
     * Formatos según periodo:
     * Anual:   {PREFIJO}-{YY}-{NUMERO}            (ej: LOT-26-00001)
     * Mensual: {PREFIJO}-{MM}-{YY}-{NUMERO}       (ej: LOT-02-26-00001)
     * Semanal: {PREFIJO}-{W}-{MM}-{YY}-{NUMERO}   (ej: LOT-4-02-26-00001)
     * Diario:  {PREFIJO}-{DD}-{MM}-{YY}-{NUMERO}  (ej: LOT-24-02-26-00001)
     * Ninguno: {PREFIJO}-{NUMERO}                  (ej: LOT-00001)
     *
     * @param string $tabla Nombre de la tabla
     * @param string $prefijo Prefijo del correlativo (ej: 'LOT', 'CH')
     * @param array<string, mixed> $filtros Filtros adicionales [columna => valor] (ej: ['id_empresa' => 5])
     * @param int $longitudCeros Padding del número (ej: 5 -> 00001)
     * @param Periodo $reseteo Periodo de reseteo de la numeración
     * @param string $columnaFecha Columna de fecha para el filtro de reseteo
     *
     * @return array{correlativo: string, numero_correlativo: int}
     *
     */
    public static function generar(
        string $tabla,
        string $prefijo,
        array $filtros = [],
        int $longitudCeros = 5,
        Periodo $reseteo = Periodo::Anual,
        string $columnaFecha = 'created_at'
    ): array {
        $query = DB::table($tabla);

        // Filtro por periodo
        $now = now();
        match ($reseteo) {
            Periodo::Diario => $query->whereDate($columnaFecha, $now->toDateString()),
            Periodo::Semanal => $query->whereBetween($columnaFecha, [
                $now->startOfWeek()->startOfDay(),
                $now->endOfWeek()->endOfDay(),
            ]),
            Periodo::Mensual => $query
                ->whereYear($columnaFecha, $now->year)
                ->whereMonth($columnaFecha, $now->month),
            Periodo::Anual => $query->whereYear($columnaFecha, $now->year),
            Periodo::Ninguno => null,
        };

        // Otros filtros
        foreach ($filtros as $col => $val) {
            $query->where($col, $val);
        }

        // Ejecutamos la query para obtener el siguiente número
        $siguienteNumero = ($query->max('numero_correlativo') ?? 0) + 1;

        // Formateamos el número con ceros a la izquierda
        $numeroFormateado = str_pad($siguienteNumero, $longitudCeros, '0', STR_PAD_LEFT);

        // Segmento de fecha según el periodo
        $segmentoFecha = match ($reseteo) {
            Periodo::Diario => $now->format('d') . '-' . $now->format('m') . '-' . $now->format('y'),
            Periodo::Semanal => $now->weekOfMonth . '-' . $now->format('m') . '-' . $now->format('y'),
            Periodo::Mensual => $now->format('m') . '-' . $now->format('y'),
            Periodo::Anual => $now->format('y'),
            Periodo::Ninguno => null,
        };

        // Retornamos el correlativo y el número
        $correlativo = $segmentoFecha
            ? "{$prefijo}-{$segmentoFecha}-{$numeroFormateado}"
            : "{$prefijo}-{$numeroFormateado}";

        return [
            'correlativo' => $correlativo,
            'numero_correlativo' => $siguienteNumero,
        ];
    }
}
