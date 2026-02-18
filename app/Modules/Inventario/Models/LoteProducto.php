<?php

namespace App\Modules\Inventario\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    /**
     * Listar lotes de un almacén.
     */
    public static function get_lotes_by_almacen(int $id_almacen)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            p.nombre AS producto,
            c.nombre AS categoria,
            lp.id_unidad_medida,
            um.abreviatura AS unidad_medida,
            lp.id_almacen,
            lp.descripcion,
            CONCAT(lp.correlativo, \'-\', LPAD(lp.numero_correlativo, 3, \'0\')) AS codigo_lote,
            lp.stock_actual,
            lp.fecha_ingreso,
            lp.fecha_vencimiento,
            lp.estado
        FROM
            lote_producto lp
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN categoria c ON c.id = p.id_categoria
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        WHERE
            lp.id_almacen = :id_almacen AND
            lp.estado = :estado
        ORDER BY lp.created_at DESC
        ';

        return DB::select($sql, [
            'id_almacen' => $id_almacen,
            'estado'     => EstadoBase::Activo->value
        ]);
    }

    /**
     * Obtener lote por ID (para retorno post-creación).
     */
    public static function get_lote_by_id(int $id)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            p.nombre AS producto,
            c.nombre AS categoria,
            lp.id_unidad_medida,
            um.abreviatura AS unidad_medida,
            lp.id_almacen,
            lp.descripcion,
            CONCAT(lp.correlativo, \'-\', LPAD(lp.numero_correlativo, 3, \'0\')) AS codigo_lote,
            lp.stock_actual,
            lp.fecha_ingreso,
            lp.fecha_vencimiento,
            lp.estado
        FROM
            lote_producto lp
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN categoria c ON c.id = p.id_categoria
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        WHERE
            lp.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    public static function get_ultimo_correlativo()
    {
        return DB::table('lote_producto')->max('numero_correlativo') ?? 0;
    }

    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        string $correlativo,
        int $numero_correlativo,
        float $stock_inicial,
        string $fecha_ingreso,
        ?string $fecha_vencimiento
    ) {
        return DB::table('lote_producto')->insertGetId([
            'id_producto'        => $id_producto,
            'id_unidad_medida'   => $id_unidad_medida,
            'id_almacen'         => $id_almacen,
            'descripcion'        => $descripcion,
            'correlativo'        => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'stock_actual'       => $stock_inicial,
            'fecha_ingreso'      => $fecha_ingreso,
            'fecha_vencimiento'  => $fecha_vencimiento,
            'created_at'         => now(),
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    /**
     * Obtener productos disponibles para sugerir.
     */
    public static function get_productos_para_lote()
    {
        $sql = '
        SELECT
            p.id AS id_producto,
            p.nombre,
            c.nombre as categoria,
            p.es_perecible
        FROM
            producto p
        INNER JOIN categoria c ON c.id = p.id_categoria
        WHERE
            p.estado = :estado AND
            c.tipo_requerimiento = :tipo_bien
        ORDER BY p.nombre ASC
        ';
        
        return DB::select($sql, [
            'estado'    => EstadoBase::Activo->value,
            'tipo_bien' => 'Bien'
        ]);
    }
}
