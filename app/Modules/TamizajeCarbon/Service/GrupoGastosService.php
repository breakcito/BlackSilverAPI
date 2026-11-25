<?php

namespace App\Modules\TamizajeCarbon\Service;

use App\Modules\TamizajeCarbon\Data\GrupoGastosData;
use App\Shared\Enums\Carbon\TipoOperacionGrupoGastos;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

/**
 * Grupos de gastos de produccion.
 *
 * Un grupo reune varios zarandeos (o varios blendings) de una misma
 * operacion para que los gastos de esa operacion se registren una sola vez
 * contra el grupo.
 *
 * Los grupos de zarandeo y los de blending son independientes: cada grupo
 * pertenece a un solo tipo de operacion.
 */
class GrupoGastosService
{
    /**
     * Grupos de gastos de un tipo de operacion con sus totales.
     *
     * @param string $tipo TipoOperacionGrupoGastos: 'zarandeo' | 'blending'
     */
    public static function get_grupos(string $tipo): array
    {
        return ApiResponse::success(GrupoGastosData::get_grupos($tipo));
    }

    /**
     * Crea un grupo de gastos y le asigna las operaciones indicadas.
     *
     * @param array<int, int> $ids_miembros
     */
    public static function crear_grupo(
        string $tipo,
        array $ids_miembros,
        int $id_empleado
    ): array {
        $tipoEnum = TipoOperacionGrupoGastos::tryFrom($tipo);
        if ($tipoEnum === null) {
            return ApiResponse::error('El tipo de operacion del grupo no es valido');
        }

        $ids = array_values(array_unique(array_map('intval', $ids_miembros)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));

        if (empty($ids)) {
            return ApiResponse::error('Seleccione al menos una operacion para agrupar');
        }

        $validacion = self::validar_miembros($ids, $tipoEnum);
        if ($validacion !== null) {
            return $validacion;
        }

        $idGrupo = GrupoGastosData::crear_grupo(
            tipo: $tipoEnum->value,
            ids_miembros: $ids,
            id_empleado: $id_empleado
        );

        return ApiResponse::success($idGrupo, 'Operación agrupada');
    }

    /**
     * Verifica que las operaciones existan, sean del tipo esperado y no
     * pertenezcan ya a otro grupo.
     *
     * @param array<int, int> $ids
     */
    private static function validar_miembros(array $ids, TipoOperacionGrupoGastos $tipo): ?array
    {
        $tabla = $tipo === TipoOperacionGrupoGastos::Blending
            ? 'blending_carbon'
            : 'zarandeo_carbon';

        $filas = DB::table($tabla)
            ->whereIn('id', $ids)
            ->get(['id', 'id_grupo_gastos']);

        if ($filas->count() !== count($ids)) {
            return ApiResponse::error('Alguna de las operaciones seleccionadas ya no existe');
        }

        foreach ($filas as $fila) {
            if ($fila->id_grupo_gastos !== null) {
                return ApiResponse::error(
                    'Alguna de las operaciones seleccionadas ya pertenece a otro grupo de gastos'
                );
            }
        }

        return null;
    }
}
