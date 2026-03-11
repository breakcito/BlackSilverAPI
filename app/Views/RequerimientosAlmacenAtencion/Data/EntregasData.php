<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use App\Models\RequerimientoAlmacenEntrega;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class EntregasData
{

    /**
     * Obtener el historial de entregas en base al detalle de un requerimiento
     */
    public static function get_historial_entregas(?int $id_detalle_requerimiento = null, ?int $id_entrega = null)
    {
        $sql = '
        SELECT DISTINCT
            ent.id AS id_requerimiento_almacen_entrega,
            CONCAT(emp_ent.nombre," ",emp_ent.apellido) AS empleado_entrega,
            CONCAT(emp_rec.nombre," ",emp_rec.apellido) AS empleado_recibe,
            ent.correlativo,
            ent.fecha_hora_entrega,
            ent.observacion,
            ent.evidencias,
            ent.created_at,
            ent.estado
        FROM
            requerimiento_almacen_entrega_detalle raed
        INNER JOIN requerimiento_almacen_entrega ent ON
            ent.id = raed.id_requerimiento_almacen_entrega
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

        if ($id_detalle_requerimiento) {
            $sql .= ' AND raed.id_requerimiento_almacen_detalle = :id_detalle_requerimiento';
            $params['id_detalle_requerimiento'] = $id_detalle_requerimiento;
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
     * obtener la lista de almacenes donde el empleado es responsable
     */
    public static function get_almacenes(int $id_empleado): array
    {
        $sql = '
        SELECT DISTINCT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN responsable_almacen res ON
            res.id_almacen = alm.id
        WHERE
            alm.estado = "Activo" AND
            alm.es_principal != 1 AND 
            res.estado = "Activo" AND 
            res.id_empleado = :id_empleado
        ';

        return DB::select($sql, ['id_empleado' => $id_empleado]);
    }

    /**
     * Obtiene los requerimientos de almacen por atender/atendidos
     */
    public static function get_resumen_requerimientos(
        int $id_almacen,
        string $mes,
        string $yearcito,
    ) {
        $sql = '
        SELECT
            ra.id AS id_requerimiento,
            ra.id_almacen_destino,
            ra.correlativo,
            ra.observacion,
            m.nombre AS mina,
            CONCAT(emp.nombre, " ", emp.apellido) AS solicitante,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at
        FROM
            requerimiento_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN mina m ON m.id = ra.id_mina
        WHERE
            ra.id_almacen_destino = :id_almacen_destino AND
            MONTH(ra.created_at) = :mes AND YEAR(ra.created_at) = :yearcito
        ORDER BY 
        	CASE ra.estado
                WHEN "Generado"  THEN 1
                WHEN "En Proceso" THEN 2
                WHEN "Cerrado" THEN 3
                WHEN "Anulado" THEN 4
            	ELSE 5 
            END ASC,
        	ra.created_at DESC
        ';

        $params = [
            'id_almacen_destino' => $id_almacen,
            'mes' => $mes,
            'yearcito' => $yearcito,
        ];

        return DB::select($sql, $params);
    }

    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles_by_requerimiento(
        int $id_requerimiento
    ) {
        // 1. Definimos la base de la consulta (sin WHERE ni ORDER BY aún)
        $sql = '
    SELECT
        rad.id AS id_requerimiento_almacen_detalle,
        CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
        pr.nombre AS producto,
        unib.abreviatura AS unidad_medida_base,
        uni.abreviatura AS unidad_medida,
        rad.contenido_por_presentacion,
        rad.cantidad_solicitada,
        rad.cantidad_solicitada_base,
        rad.cantidad_entregada,
        rad.cantidad_entregada_base,
        CASE 
            WHEN rad.cantidad_solicitada_base > 0 THEN 
                ROUND(((rad.cantidad_entregada_base / rad.cantidad_solicitada_base) * 100 ), 0)
            ELSE 0 
        END AS porcentaje_progreso,
        rad.comentario,
        rad.comentario_decision,
        rad.estado
    FROM
        requerimiento_almacen_detalle rad
    INNER JOIN producto pr ON pr.id = rad.id_producto
    LEFT JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
    LEFT JOIN unidad_medida uni ON uni.id = rad.id_unidad_medida
    LEFT JOIN empleado emp ON emp.id = rad.id_empleado_atencion
    WHERE 1=1';

        $params = [];
        $sql .= ' AND rad.id_requerimiento_almacen = :id_requerimiento';
        $params['id_requerimiento'] = $id_requerimiento;

        $sql .= ' ORDER BY pr.nombre';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene las labores asociadas a un requerimiento de almacen
     */
    public static function get_labores_by_requerimiento(int $id_requerimiento)
    {
        return Labor::get_labores_by_requerimiento(id_requerimiento: $id_requerimiento);
    }

    /**
     * Consultas utiles para el registro de una entrega
     */

    /**
     * Obtener el nuevo correlativo para un entrega en
     * base al almacen de destino
     */
    public static function get_nuevo_correlativo(int $id_almacen_destino)
    {
        return CorrelativoHelper::generar(
            prefijo: 'ENT',
            tabla: 'requerimiento_almacen_entrega',
            alias: 'ent',
            queryModifier: function ($query) {
                // conectamos las entregas con los requerimientos para filtrar por almacen
                $query->join(
                    'requerimiento_almacen as req',
                    'req.id',
                    '=',
                    'ent.id_requerimiento_almacen'
                );
            },
            filtros: ['req.id_almacen_destino' => $id_almacen_destino],
        );
    }

    /**
     * Crear una nueva entrega
     */
    public static function crear_entrega(
        int $id_requerimiento,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        int $fecha_hora_entrega,
        ?string $observacion = null,
    ) {
        return RequerimientoAlmacenEntrega::insertGetId([
            'id_requerimiento_almacen' => $id_requerimiento,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_entrega' => $fecha_hora_entrega,
            'observacion' => $observacion,
            'created_at' => now(),
            'estado' => 'Procesado'
        ]);
    }

    // obtener la lista de empleados para indicar quien recibe
    public static function get_empleados()
    {
        return DB::select('
        SELECT DISTINCT
            emp.id AS id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) AS nombre_completo,
            emp.dni,
            emp.path_foto
        FROM
            empleado emp
        WHERE
            emp.estado = "Activo"
        ');
    }

    /**
     * Obtiene los lotes disponibles para un producto en un almacén
     */
    public static function get_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        return DB::select('
            SELECT 
                lp.id AS id_lote,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                uni.nombre AS unidad_medida,
                uni.abreviatura AS unidad_medida_abv,
                lp.fecha_hora_ingreso,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM 
                lote_producto lp
            INNER JOIN unidad_medida uni ON uni.id = lp.id_unidad_medida
            WHERE 
                lp.id_producto = :id_producto AND 
                lp.id_almacen = :id_almacen AND 
                lp.stock_actual_base > 0 AND
                lp.estado = "Activo"
            ORDER BY 
                lp.fecha_vencimiento ASC, 
                lp.created_at ASC
        ', [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen
        ]);
    }

    /**
     * Actualiza el estado de un detalle de requerimiento
     */
    public static function update_detalle_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
            'id_empleado_atencion' => $id_empleado
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return DB::table('requerimiento_almacen_detalle')
            ->where('id', $id_detalle)
            ->update($updateData);
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(int $id_detalle, int $id_empleado, string $descripcion, string $estado)
    {
        return DB::table('requerimiento_almacen_detalle_log')->insert([
            'id_requerimiento_almacen_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now()
        ]);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        return DB::select('
            SELECT DISTINCT
                trz.id AS id_requerimiento_almacen_detalle_log,
                CASE
                    WHEN trz.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = trz.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                trz.descripcion,
                trz.created_at,
                trz.estado
            FROM
                requerimiento_almacen_detalle_log trz
            WHERE
                trz.id_requerimiento_almacen_detalle = :id_detalle
            ORDER BY trz.created_at
        ', ["id_detalle" => $id_detalle]);
    }

    /**
     * Insertar detalle de entrega
     */
    public static function insert_entrega_detalle(
        int $id_entrega,
        int $id_detalle,
        int $id_lote,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_requerimiento
    ) {
        return DB::table('requerimiento_almacen_entrega_detalle')->insert([
            'id_requerimiento_almacen_entrega' => $id_entrega,
            'id_requerimiento_almacen_detalle' => $id_detalle,
            'id_lote_producto' => $id_lote,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_requerimiento' => $cantidad_requerimiento,
            'created_at' => now(),
            'estado' => 'Entregado'
        ]);
    }

    /**
     * Actualizar stock del lote
     */
    public static function update_lote_stock(int $id_lote, float $cantidad_lote, float $cantidad_base)
    {
        return DB::table('lote_producto')
            ->where('id', $id_lote)
            ->decrementEach([
                'stock_actual' => $cantidad_lote,
                'stock_actual_base' => $cantidad_base
            ], [
                'updated_at' => now()
            ]);
    }

    /**
     * Insertar en Kardex
     */
    public static function insert_kardex(
        int $id_lote,
        int $id_origen,
        string $tipo_origen,
        string $tipo_movimiento,
        float $stock_ant,
        float $stock_ant_base,
        float $cant_mov,
        float $cant_mov_base,
        float $stock_res,
        float $stock_res_base,
        string $descripcion
    ) {
        return DB::table('kardex_producto')->insert([
            'id_lote_producto' => $id_lote,
            'id_origen' => $id_origen,
            'tipo_origen' => $tipo_origen,
            'tipo_movimiento' => $tipo_movimiento,
            'stock_anterior' => $stock_ant,
            'stock_anterior_base' => $stock_ant_base,
            'cantidad_movimiento' => $cant_mov,
            'cantidad_movimiento_base' => $cant_mov_base,
            'stock_resultante' => $stock_res,
            'stock_resultante_base' => $stock_res_base,
            'descripcion' => $descripcion,
            'created_at' => now()
        ]);
    }

    /**
     * Incrementar cantidades entregadas en el detalle del requerimiento
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_req, float $cantidad_base)
    {
        return DB::table('requerimiento_almacen_detalle')
            ->where('id', $id_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_req,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }

    /**
     * Verificar si el requerimiento está completado
     */
    public static function check_requerimiento_completado(int $id_requerimiento): bool
    {
        $pendientes = DB::table('requerimiento_almacen_detalle')
            ->where('id_requerimiento_almacen', $id_requerimiento)
            ->whereNotIn('estado', ['Completado', 'Cerrado', 'Rechazado - Almacen', 'Anulado'])
            ->count();

        return $pendientes === 0;
    }

    /**
     * Actualizar estado del requerimiento
     */
    public static function update_requerimiento_estado(int $id_requerimiento, string $estado)
    {
        return DB::table('requerimiento_almacen')
            ->where('id', $id_requerimiento)
            ->update([
                'estado' => $estado,
                'updated_at' => now()
            ]);
    }
}
