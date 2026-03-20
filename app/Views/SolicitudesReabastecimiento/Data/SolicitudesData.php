<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimiento;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitud;
use Illuminate\Support\Facades\DB;

class SolicitudesData
{
    // Obtener una o toda la lista de solicitudes hechas por un usuario
    public static function get_solicitudes(
        ?int $id_solicitud = null,
        ?int $id_empleado = null,
        ?int $mes = null,
        ?int $yearcito = null,
    ) {
        $sql = "
        SELECT
            sr.id AS id_solicitud,
            sr.id_almacen_solicitante,
            sr.id_requerimiento_almacen,
            sr.observacion,
            req.correlativo as correlativo_requerimiento,
            alm.nombre AS almacen_solicitante,
            CONCAT(em.nombre, ' ', em.apellido) AS empleado_solicitante,
            sr.correlativo,
            sr.premura,
            sr.fecha_entrega_requerida,
            sr.created_at,
            sr.estado
        FROM
            solicitud_reabastecimiento sr
        INNER JOIN empleado em ON
            em.id = sr.id_empleado_solicitante
        INNER JOIN almacen alm ON
            alm.id = sr.id_almacen_solicitante
        LEFT JOIN requerimiento_almacen req on req.id = sr.id_requerimiento_almacen
        WHERE
            1 = 1
        ";

        $params = [];

        // Si se busca por id, devolvemos solo ese registro
        if ($id_solicitud !== null) {
            $sql .= ' AND sr.id = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud;
            return DB::selectOne($sql, $params);
        }

        // Por empleado
        if ($id_empleado !== null) {
            $sql .= ' AND sr.id_empleado_solicitante = :id_empleado';
            $params['id_empleado'] = $id_empleado;
        }

        // Por periodo
        if ($mes !== null) {
            $sql .= ' AND MONTH(sr.created_at) = :mes';
            $params['mes'] = $mes;
        }

        if ($yearcito !== null) {
            $sql .= ' AND YEAR(sr.created_at) = :yearcito';
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY sr.created_at DESC';

        return DB::select($sql, $params);
    }

    // Obtener una solicitud
    public static function get_solicitud_by_id(int $id_solicitud)
    {
        return self::get_solicitudes(id_solicitud: $id_solicitud);
    }


    // Funcion helpder que ayuda a crear la cabecera de la solicitud
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        ?string $observacion = null,
        string $premura,
        ?string $fecha_entrega_requerida = null,   
    ) {
        return SolicitudReabastecimiento::insertGetId([
            'id_almacen_solicitante' => $id_almacen_solicitante,
            'id_empleado_solicitante' => $id_empleado_solicitante,
            'id_requerimiento_almacen' => null,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'premura' => $premura,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'created_at' => now(),
            'estado' => EstadoSolicitud::Generada->value,
        ]);
    }


    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo(int $id_almacen_solicitante)
    {
        return SolicitudReabastecimiento::get_nuevo_correlativo($id_almacen_solicitante);
    }

    // Obtener el historial de entregas para una solicitud
    public static function get_historial_entregas(int $id_solicitud)
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
        WHERE ent.id_solicitud_reabastecimiento = :id_solicitud
        ORDER BY ent.correlativo DESC;
        ';

        return DB::select($sql, ['id_solicitud' => $id_solicitud]);
    }

    // Obtener detalles de una entrega
    public static function get_detalles_entrega(int $id_entrega)
    {
        $sql = "
        SELECT
            red.id AS id_entrega_detalle,
            red.id_solicitud_reabastecimiento_detalle,
            lot.id_producto,
            lot.id_unidad_medida,
            lot.correlativo,
            lot.fecha_vencimiento,
            prod.nombre as producto,
            red.cantidad_base,
            red.cantidad_lote,
            red.cantidad_solicitud,
            uni_lot.abreviatura as unidad_lote_abv,
            uni_base.abreviatura as unidad_base_abv,
            red.estado as estado_entrega_detalle
        FROM
            solicitud_reabastecimiento_entrega_detalle red
        INNER JOIN lote_producto lot ON
            lot.id = red.id_lote_producto
        INNER JOIN producto prod ON
            prod.id = lot.id_producto
        INNER JOIN unidad_medida uni_base ON
            uni_base.id = prod.id_unidad_medida_base
        INNER JOIN unidad_medida uni_lot ON
            uni_lot.id = lot.id_unidad_medida
        WHERE red.id_reabastecimiento_entrega = :id_entrega
        ORDER BY lot.correlativo DESC;
        ";

        return DB::select($sql, ['id_entrega' => $id_entrega]);
    }

    // Obtener los lotes disponibles del almacén destino para los productos solicitados
    public static function get_lotes_destino_disponibles(int $id_reabastecimiento_entrega)
    {
        $sql = "
            SELECT 
                lp.id AS id_lote,
                lp.id_producto,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                lp.id_unidad_medida,
                pr.id_unidad_medida_base,
                uni.nombre AS unidad_medida,
                uni.abreviatura AS unidad_medida_abv,
                lp.fecha_hora_ingreso,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM 
                lote_producto lp
            INNER JOIN unidad_medida uni ON uni.id = lp.id_unidad_medida
            INNER JOIN producto pr ON pr.id = lp.id_producto
            INNER JOIN solicitud_reabastecimiento sr ON sr.id_almacen_solicitante = lp.id_almacen
            INNER JOIN solicitud_reabastecimiento_entrega sre ON sre.id_solicitud_reabastecimiento = sr.id AND sre.id = :id_entrega
            INNER JOIN solicitud_reabastecimiento_entrega_detalle sred ON sred.id_reabastecimiento_entrega = sre.id
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = sred.id_solicitud_reabastecimiento_detalle AND srd.id_producto = lp.id_producto
            WHERE 
                lp.estado = 'Activo'
            GROUP BY lp.id
            ORDER BY 
                lp.fecha_vencimiento ASC, 
                lp.created_at ASC
        ";

        return DB::select($sql, ['id_entrega' => $id_reabastecimiento_entrega]);
    }

    // Obtener información de un detalle de entrega
    public static function get_entrega_detalle_info(int $id_reabastecimiento_entrega, int $id_solicitud_detalle)
    {
        return DB::table('solicitud_reabastecimiento_entrega_detalle as sred')
            ->join('solicitud_reabastecimiento_entrega as sre', 'sre.id', '=', 'sred.id_reabastecimiento_entrega')
            ->join('solicitud_reabastecimiento as sr', 'sr.id', '=', 'sre.id_solicitud_reabastecimiento')
            ->join('solicitud_reabastecimiento_detalle as srd', 'srd.id', '=', 'sred.id_solicitud_reabastecimiento_detalle')
            ->where('sred.id_reabastecimiento_entrega', $id_reabastecimiento_entrega)
            ->where('sred.id_solicitud_reabastecimiento_detalle', $id_solicitud_detalle)
            ->select(
                'sred.id as id_entrega_detalle',
                'sred.cantidad_base',
                'sred.cantidad_lote',
                'sre.correlativo as correlativo_entrega',
                'sr.correlativo as correlativo_solicitud',
                'sr.id_almacen_solicitante',
                'srd.id_producto',
                'srd.estado as estado_solicitud_detalle',
                'sred.estado as estado_entrega_detalle',
                'srd.id_unidad_medida'
            )
            ->first();
    }

    // Registrar recepcion en lote existente
    public static function registrar_recepcion_lote_existente(int $id_lote, float $cantidad_lote, float $cantidad_base)
    {
        $lote = DB::table('lote_producto')->where('id', $id_lote)->first();
        if (!$lote) return null;

        DB::table('lote_producto')->where('id', $id_lote)->update([
            'stock_actual' => DB::raw("stock_actual + $cantidad_lote"),
            'stock_actual_base' => DB::raw("stock_actual_base + $cantidad_base"),
        ]);

        return $lote;
    }

    // Registrar recepcion en nuevo lote
    public static function registrar_recepcion_lote_nuevo(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $fecha_vencimiento,
        float $cantidad_lote,
        float $cantidad_base
    ) {
        $correlativoData = \App\Shared\Helpers\CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LOT',
            filtros: ['id_almacen' => $id_almacen],
            columnaFecha: 'fecha_hora_ingreso'
        );

        return DB::table('lote_producto')->insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'descripcion' => 'Lote generado por recepción de entrega',
            'correlativo' => $correlativoData['correlativo'], 
            'numero_correlativo' => $correlativoData['numero_correlativo'],
            'stock_actual' => $cantidad_lote,
            'contenido_por_presentacion' => ($cantidad_lote > 0) ? ($cantidad_base / $cantidad_lote) : 1,
            'stock_actual_base' => $cantidad_base,
            'fecha_hora_ingreso' => now(),
            'fecha_vencimiento' => $fecha_vencimiento,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
            'created_at' => now(),
        ]);
    }

    // Registrar ingreso al kardex
    public static function registrar_kardex_recepcion(
        int $id_lote_producto,
        int $id_origen,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        string $descripcion
    ) {
        $lote = DB::table('lote_producto')->where('id', $id_lote_producto)->first();

        // El stock después de ajustar (como ya lo ajustamos arriba)
        $stock_resultante = $lote->stock_actual;
        $stock_resultante_base = $lote->stock_actual_base;
        $stock_anterior = $stock_resultante - $cantidad_movimiento;
        $stock_anterior_base = $stock_resultante_base - $cantidad_movimiento_base;

        return \App\Models\KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote_producto,
            'id_origen' => $id_origen,
            'tipo_origen' => \App\Shared\Enums\Kardex\OrigenMovimiento::Recepcion->value,
            'tipo_movimiento' => \App\Shared\Enums\Kardex\TipoMovimiento::Ingreso->value,
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_movimiento,
            'cantidad_movimiento_base' => $cantidad_movimiento_base,
            'stock_resultante' => $stock_resultante,
            'stock_resultante_base' => $stock_resultante_base,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }

    // Marcar el detalle de la entrega como recibido
    public static function marcar_entrega_detalle_como_recibido(int $id_entrega_detalle)
    {
        return DB::table('solicitud_reabastecimiento_entrega_detalle')
            ->where('id', $id_entrega_detalle)
            ->update([
                'estado' => \App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega::Recibido->value
            ]);
    }

    // Verificar y completar la entrega si corresponde
    public static function verificar_y_completar_entrega(int $id_reabastecimiento_entrega)
    {
        $pendientes = DB::table('solicitud_reabastecimiento_entrega_detalle')
            ->where('id_reabastecimiento_entrega', $id_reabastecimiento_entrega)
            ->where('estado', '!=', \App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega::Recibido->value)
            ->where('estado', '!=', \App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega::Anulado->value)
            ->count();
        
        if ($pendientes === 0) {
            DB::table('solicitud_reabastecimiento_entrega')
                ->where('id', $id_reabastecimiento_entrega)
                ->update([
                    'estado' => \App\Shared\Enums\SolicitudReabastecimiento\EstadoEntrega::Recibida->value
                ]);
        }
    }
}
