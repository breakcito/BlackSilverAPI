<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimientoEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoEntrega;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class EntregasData
{

    /**
     * Obtener el historial de entregas en base a una solicitud
     */
    public static function get_historial_entregas(?int $id_solicitud = null, ?int $id_entrega = null)
    {
        $sql = '
        SELECT DISTINCT
            ent.id AS id_reabastecimiento_entrega,
            ent.id_almacen_entrega,
            alm.nombre as almacen_entrega,
            CONCAT(emp_ent.nombre," ",emp_ent.apellido) AS empleado_entrega,
            CONCAT(emp_rec.nombre," ",emp_rec.apellido) AS empleado_recibe,
            ent.correlativo,
            ent.fecha_hora_entrega,
            ent.observacion,
            ent.evidencias,
            ent.created_at,
            ent.estado
        FROM
            solicitud_reabastecimiento_entrega ent
        INNER JOIN almacen alm on alm.id = ent.id_almacen_entrega
        LEFT JOIN empleado emp_ent ON
            emp_ent.id = ent.id_empleado_entrega
        LEFT JOIN empleado emp_rec ON
            emp_rec.id = ent.id_empleado_recibe
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_entrega) {
            $sql .= ' AND ent.id = :id_entrega';
            $params['id_entrega'] = $id_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_solicitud) {
            $sql .= ' AND ent.id_solicitud_reabastecimiento = :id_solicitud';
            $params['id_solicitud'] = $id_solicitud;
        }

        $sql .= ' ORDER BY ent.correlativo DESC;';

        return DB::select($sql, $params);
    }

    /**
     * Obtener una entrega
     */
    public static function get_entrega_by_id(int $id_entrega)
    {
        return self::get_historial_entregas(id_entrega: $id_entrega);
    }

    /**
     * Obtener el nuevo correlativo para un entrega en
     * base al almacen que entrega
     */
    public static function get_nuevo_correlativo(int $id_almacen_entrega)
    {
        return CorrelativoHelper::generar(
            prefijo: 'ENT',
            tabla: 'solicitud_reabastecimiento_entrega',
            filtros: ['id_almacen_entrega' => $id_almacen_entrega],
        );
    }

    /**
     * Crear una nueva entrega
     */
    public static function crear_entrega(
        int $id_solicitud,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_entrega,
        ?string $observacion = null,
        ?array $evidencias = null,
    ) {
        return SolicitudReabastecimientoEntrega::insertGetId([
            'id_solicitud_reabastecimiento' => $id_solicitud,
            'id_almacen_entrega' => $id_almacen_entrega,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_entrega' => $fecha_hora_entrega,
            'observacion' => $observacion,
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'created_at' => now(),
            'estado' => EstadoEntrega::Procesada->value
        ]);
    }
}
