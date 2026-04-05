<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use App\Models\RequerimientoAlmacen;

class RequerimientosData
{

    /**
     * Obtiene los requerimientos de almacen por atender/atendidos
     */
    public static function get_resumen_requerimientos(
        int $id_almacen,
        string $mes,
        string $yearcito,
    ) {
        return RequerimientoAlmacen::get_requerimientos(
            id_almacen_destino: $id_almacen,
            mes: $mes,
            yearcito: $yearcito
        );
    }

    /**
     * Obtiene las labores asociadas a un requerimiento de almacen
     */
    public static function get_labores_by_requerimiento(int $id_requerimiento)
    {
        return Labor::get_labores_by_requerimiento(id_requerimiento: $id_requerimiento);
    }

    /**
     * Obtener almacen de destino de un requerimiento de almacen
     */
    public static function get_almacen_destino_by_requerimiento(int $id_requerimiento)
    {
        return RequerimientoAlmacen::select('id_almacen_destino')
            ->where('id', $id_requerimiento)
            ->first();
    }

    public static function update_requerimiento_estado(int $id_requerimiento, string $estado)
    {
        return RequerimientoAlmacen::where('id', $id_requerimiento)
            ->update([
                'estado' => $estado
            ]);
    }

    public static function get_correlativo_by_requerimiento(int $id_requerimiento)
    {
        return RequerimientoAlmacen::select('correlativo')
            ->where('id', $id_requerimiento)
            ->first();
    }
}
