<?php

namespace App\Models;

use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_unidad_medida',
        'id_almacen',
        'descripcion',
        'correlativo',
        'numero_correlativo',
        'stock_actual',
        'contenido_por_presentacion',
        'stock_actual_base',
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        'created_at',
        'estado',
    ];

    public static function get_nuevo_correlativo(int $id_almacen)
    {
        return CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LOT',
            filtros: ['id_almacen' => $id_almacen],
            columnaFecha: 'fecha_hora_ingreso'
        );
    }

    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        string $correlativo,
        int $numero_correlativo,
        float $stock_inicial,
        float $contenido_por_presentacion,
        float $stock_actual_base,
        string $fecha_hora_ingreso,
        ?string $fecha_vencimiento
    ) {
        return self::insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'descripcion' => $descripcion,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'stock_actual' => $stock_inicial,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $stock_actual_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
            'fecha_vencimiento' => $fecha_vencimiento,
            'created_at' => now(),
            'estado' => 'Activo',
        ]);
    }

    public static function update_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base)
    {
        return self::where('id', $id_lote)->update([
            'stock_actual' => $nuevo_stock,
            'stock_actual_base' => $nuevo_stock_base,
        ]);
    }

    public static function get_lote_simple_by_id(int $id_lote): array
    {
        return self::where('id', $id_lote)
            ->first([
                'id as id_lote',
                'id_producto',
                'id_unidad_medida',
                'id_almacen',
                'correlativo',
                'stock_actual',
                'stock_actual_base',
                'contenido_por_presentacion'
            ])?->toArray();
    }

    /**w
     * Obtener los lotes disponibles de un almacen, util para cualquier 
     * tipo de entregas o ingresos. Solo se traen lotes activos, con stock y
     * no vencidos.
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        // 1. Creamos los placeholders (?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            lp.correlativo,
            lp.stock_actual,
            lp.stock_actual_base,
            lp.contenido_por_presentacion,
            uni.id as id_unidad_medida_lote,
            uni.nombre AS unidad_medida_lote,
            uni.abreviatura AS unidad_medida_lote_abv,
            unib.id as id_unidad_medida_base,
            unib.nombre AS unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer,
            CASE 
                WHEN pr.es_perecible != 1 THEN 'N/A' 
                WHEN lp.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= pr.dias_espera_vencimiento THEN 'Por vencer' 
                ELSE 'Vigente'
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN unidad_medida uni ON uni.id = lp.id_unidad_medida
        INNER JOIN producto pr ON pr.id = lp.id_producto
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        WHERE
            lp.id_producto IN ($placeholders) AND 
            lp.id_almacen = ? AND 
            lp.stock_actual_base > 0 AND 
            lp.estado = 'Activo' AND
            -- no traer vencidos
            (lp.fecha_vencimiento IS NULL OR DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) >= 0)
        ORDER BY
            CASE 
                WHEN lp.fecha_vencimiento IS NULL THEN 3 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= pr.dias_espera_vencimiento THEN 1 
                ELSE 2 
            END ASC,
            lp.fecha_vencimiento ASC,
            lp.fecha_hora_ingreso ASC,
            lp.created_at ASC
        ";

        $params = array_merge($ids_productos, [$id_almacen]);

        return DB::select($sql, $params);
    }
}
