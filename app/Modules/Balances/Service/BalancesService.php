<?php

namespace App\Modules\Balances\Service;

use App\Modules\Balances\Data\BalancesData;
use App\Shared\Enums\Balances\OrigenBalance;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BalancesService
{
    /**
     * Genera el balance: todos los ingresos y egresos del periodo actual y
     * del periodo de comparacion, en una sola lista de movimientos.
     *
     * El API NO devuelve totales: entrega los movimientos crudos de ambos
     * periodos y es el front quien los resume por moneda, ambito y categoria.
     * Asi las tarjetas de indicador, el grafico, la tabla comparativa y el
     * desglose salen SIEMPRE de la misma fuente y no pueden contradecirse,
     * y los filtros dinamicos recalculan al instante sin volver al servidor.
     *
     * @param string      $fecha_inicio            Fecha inicial del periodo (Y-m-d).
     * @param string      $fecha_fin               Fecha final del periodo (Y-m-d).
     * @param string|null $fecha_inicio_comparativo Inicio del periodo de comparacion.
     * @param string|null $fecha_fin_comparativo   Fin del periodo de comparacion.
     */
    public static function get_balance(
        string $fecha_inicio,
        string $fecha_fin,
        ?string $fecha_inicio_comparativo = null,
        ?string $fecha_fin_comparativo = null
    ) {
        try {
            $inicio = Carbon::parse($fecha_inicio)->startOfDay();
            $fin = Carbon::parse($fecha_fin)->startOfDay();

            if ($fin->lt($inicio)) {
                return ApiResponse::error('La fecha final del periodo no puede ser anterior a la fecha inicial.');
            }

            // Sin comparacion explicita se compara contra el periodo
            // inmediatamente anterior y de la misma duracion.
            if (!$fecha_inicio_comparativo || !$fecha_fin_comparativo) {
                $dias = $inicio->diffInDays($fin) + 1;
                $comparativo_inicio = $inicio->copy()->subDays($dias);
                $comparativo_fin = $inicio->copy()->subDay();
            } else {
                $comparativo_inicio = Carbon::parse($fecha_inicio_comparativo)->startOfDay();
                $comparativo_fin = Carbon::parse($fecha_fin_comparativo)->startOfDay();
            }

            $movimientos_actual = self::marcar_periodo(
                BalancesData::get_movimientos($inicio->toDateString(), $fin->toDateString()),
                'ACTUAL'
            );

            $movimientos_comparativo = self::marcar_periodo(
                BalancesData::get_movimientos($comparativo_inicio->toDateString(), $comparativo_fin->toDateString()),
                'COMPARATIVO'
            );

            return ApiResponse::success([
                'periodo' => [
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin' => $fin->toDateString(),
                    'dias' => $inicio->diffInDays($fin) + 1,
                ],
                'comparativo' => [
                    'fecha_inicio' => $comparativo_inicio->toDateString(),
                    'fecha_fin' => $comparativo_fin->toDateString(),
                    'dias' => $comparativo_inicio->diffInDays($comparativo_fin) + 1,
                ],
                // Origenes con su ambito, tipo y etiqueta legible: el front los
                // usa para los filtros y los titulos sin duplicar el catalogo.
                'catalogos' => [
                    'origenes' => OrigenBalance::catalogo(),
                    'monedas' => ['Soles', 'Dólares'],
                    'ambitos' => ['MINERAL', 'CARBON'],
                    'tipos' => ['INGRESO', 'EGRESO'],
                ],
                'movimientos' => array_merge($movimientos_actual, $movimientos_comparativo),
            ], 'Balance generado');
        } catch (\Exception $e) {
            Log::error('Error al generar el balance: ' . $e->getMessage());
            return ApiResponse::error('Ocurrió un error al generar el balance.');
        }
    }

    /**
     * Etiqueta cada movimiento con el periodo al que pertenece.
     *
     * @param array<int, array<string, mixed>> $movimientos
     * @return array<int, array<string, mixed>>
     */
    private static function marcar_periodo(array $movimientos, string $periodo): array
    {
        return array_map(function (array $movimiento) use ($periodo): array {
            $movimiento['periodo'] = $periodo;
            return $movimiento;
        }, $movimientos);
    }
}
