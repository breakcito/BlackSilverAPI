<?php

namespace App\Views\PrestamosAlmacen\Data;

use Illuminate\Support\Facades\DB;

class EntregasData
{

    /**
     * Obtiene el historial de entregas de un préstamo con sus detalles.
     */
    public static function get_entregas_por_prestamo(int $id_prestamo): array
    {
        return DB::select('
            SELECT
                pae.id AS id_entrega,
                pae.correlativo,
                pae.numero_correlativo,
                pae.fecha_hora_entrega,
                pae.observacion,
                pae.evidencias,
                pae.created_at,
                pae.estado,
                CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_entrega,
                CONCAT(emp_rec.nombre, " ", emp_rec.apellido) AS empleado_recibe
            FROM
                prestamo_almacen_entrega pae
            INNER JOIN empleado emp_ent ON emp_ent.id = pae.id_empleado_entrega
            LEFT JOIN empleado emp_rec ON emp_rec.id = pae.id_empleado_recibe
            WHERE pae.id_prestamo_almacen = :id_prestamo
            ORDER BY pae.created_at DESC
        ', ['id_prestamo' => $id_prestamo]);
    }
}
