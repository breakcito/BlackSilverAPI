<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_almacen',
        'id_unidad_medida_presentacion', // en que presentacion ingresa el lote al almacen (ej. caja)
        //
        'correlativo',
        'numero_correlativo',
        'descripcion',
        'cantidad_presentacion_inicial', // 2 cajas
        'cantidad_presentacion_actual', // 1 caja
        'contenido_por_presentacion', // 10kg por caja
        'stock_inicial', // 20kg
        'stock_actual', // 10kg
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        //
        'created_at',
        'estado',
    ];

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
            CONCAT(lp.correlativo, \'-\', DATE_FORMAT(lp.created_at, \'%y\'), \'-\', LPAD(lp.numero_correlativo, 5, \'0\')) AS codigo_lote,
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
            'estado' => EstadoBase::Activo->value,
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
            CONCAT(lp.correlativo, \'-\', DATE_FORMAT(lp.created_at, \'%y\'), \'-\', LPAD(lp.numero_correlativo, 5, \'0\')) AS codigo_lote,
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
            'estado' => EstadoBase::Activo->value,
            'tipo_bien' => 'Bien',
        ]);
    }
}
