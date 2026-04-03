<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;

class AuxData
{
    /**
     * Actualiza el estado de un detalle de requerimiento
     */
    public static function update_detalle_requerimiento_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
            'id_empleado_atencion' => $id_empleado
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }

    /**
     * Inserta un log de trazabilidad para un detalle de requerimiento
     */
    public static function insert_detalle_requerimiento_log(int $id_detalle, int $id_empleado, string $descripcion, string $estado)
    {
        return RequerimientoAlmacenDetalleLog::insertGetId([
            'id_requerimiento_almacen_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now()
        ]);
    }
}
