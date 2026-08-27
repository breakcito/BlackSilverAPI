<?php

namespace App\Data;

use App\Models\TarifaCarbon;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class TarifasCarbonData
{
    /**
     * Lista tarifas de carbon con JOIN al tipo de carbon.
     * Filtros: id_tarifa_carbon, id_tipo_carbon, estado.
     */
    public static function get_tarifas_carbon(
        ?int $id_tarifa_carbon = null,
        ?int $id_tipo_carbon = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $sql = '
            SELECT
                tc.id AS id_tarifa_carbon,
                tc.id_tipo_carbon,
                t.nombre AS tipo_carbon_nombre,
                t.codigo AS tipo_carbon_codigo,
                tc.inicio_porcentaje_ceniza,
                tc.fin_porcentaje_ceniza,
                tc.precio_unitario,
                tc.estado
            FROM tarifa_carbon tc
            INNER JOIN tipo_carbon t ON t.id = tc.id_tipo_carbon
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_tarifa_carbon !== null) {
            $sql .= ' AND tc.id = :id_tarifa_carbon';
            $params['id_tarifa_carbon'] = $id_tarifa_carbon;
            return DB::selectOne($sql, $params);
        }

        if ($id_tipo_carbon !== null) {
            $sql .= ' AND tc.id_tipo_carbon = :id_tipo_carbon';
            $params['id_tipo_carbon'] = $id_tipo_carbon;
        }

        if ($estado !== null) {
            $sql .= ' AND tc.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY tc.id_tipo_carbon ASC, tc.inicio_porcentaje_ceniza ASC';

        return DB::select($sql, $params);
    }

    public static function existe_tarifa_en_rango(
        int $id_tipo_carbon,
        float $inicio,
        float $fin,
        ?int $excluir_id = null,
    ): bool {
        $q = TarifaCarbon::query()
            ->where('id_tipo_carbon', $id_tipo_carbon)
            ->where('estado', EstadoBase::Activo->value)
            // El rango nuevo se solapa con uno existente si:
            //   inicio_nuevo <= fin_existente  AND  fin_nuevo >= inicio_existente
            ->where('inicio_porcentaje_ceniza', '<=', $fin)
            ->where('fin_porcentaje_ceniza', '>=', $inicio);

        if ($excluir_id !== null) {
            $q->where('id', '<>', $excluir_id);
        }

        return $q->exists();
    }

    public static function crear_tarifa_carbon(
        int $id_tipo_carbon,
        float $inicio_porcentaje_ceniza,
        float $fin_porcentaje_ceniza,
        float $precio_unitario,
    ): int {
        return TarifaCarbon::insertGetId([
            'id_tipo_carbon' => $id_tipo_carbon,
            'inicio_porcentaje_ceniza' => $inicio_porcentaje_ceniza,
            'fin_porcentaje_ceniza' => $fin_porcentaje_ceniza,
            'precio_unitario' => $precio_unitario,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
