<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimiento;
use Illuminate\Support\Facades\DB;

class SolicitudesData
{

    /**
     * Obtiene las solicitudes de reabastecimiento por atender/atendidos
     */
    public static function get_resumen_solicitudes(
        int $id_almacen,
        string $mes,
        string $yearcito,
    ) {
        return SolicitudReabastecimiento::get_solicitudes(
            id_almacen: $id_almacen,
            mes: $mes,
            yearcito: $yearcito
        );
    }

    public static function update_solicitud_estado(int $id_solicitud, string $estado)
    {
        return SolicitudReabastecimiento::where('id', $id_solicitud)
            ->update([
                'estado' => $estado
            ]);
    }

    public static function get_correlativo_by_solicitud(int $id_solicitud)
    {
        return SolicitudReabastecimiento::select('correlativo')
            ->where('id', $id_solicitud)
            ->first();
    }
}
