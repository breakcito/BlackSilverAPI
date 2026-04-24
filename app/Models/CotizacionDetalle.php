<?php

namespace App\Models;

use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoDespachoCompra;
use App\Shared\Enums\Cotizacion\EstadoCotizacionDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_comparativo_detalle', // tiene la info del producto y si vino de una solicitud de reabastecimiento
        'id_unidad_medida', // Caja
        'id_almacen_recepcionista', // Obligatorio - Es el almacen que deberia recibir esos productos
        //
        'tipo_despacho', // Recojo / Envio
        'lugar_recojo', // Para el recojo es obligatorio
        // tiempos estimados
        'tiempo_entrega', // 2
        'tiempo_entrega_periodo', // Semanas
        'tiempo_entrega_dias', // 14 dias
        // 
        'cantidad', // 2 Cajas
        'contenido_por_presentacion', // 3 Unidades (unidad de medida base del producto) por Caja
        'cantidad_base', // 6 unidades
        //
        'precio_unitario', // S/12 la caja
        'precio_unitario_base', // S/2 por unidad
        //
        'comentario',
        //
        'estado', // Aprovado, Rechazado (cuando se aprueba la cotizacion y no se elige), Pendiente (aun no se aprueba)
    ];

    // Funcion helpder que ayuda a crear un detalle de cotizacion
    public static function crear_detalle(
        int $id_cotizacion,
        int $id_comparativo_detalle,
        int $id_unidad_medida,
        int $id_almacen_recepcionista,
        //
        TipoDespachoCompra $tipo_despacho,
        //
        int $tiempo_entrega,
        Periodo $tiempo_entrega_periodo,
        int $tiempo_entrega_dias,
        //
        float $cantidad,
        float $contenido_por_presentacion,
        float $cantidad_base,
        //
        float $precio_unitario,
        float $precio_unitario_base,
        //
        ?string $comentario = null,
        ?string $lugar_recojo = null,
        //
        EstadoCotizacionDetalle $estado = EstadoCotizacionDetalle::Pendiente
    ) {
        return CotizacionDetalle::insertGetId([
            'id_cotizacion' => $id_cotizacion,
            'id_comparativo_detalle' => $id_comparativo_detalle,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen_recepcionista' => $id_almacen_recepcionista,
            //
            'tipo_despacho' => $tipo_despacho->value,
            'lugar_recojo' => $lugar_recojo,
            //
            'tiempo_entrega' => $tiempo_entrega,
            'tiempo_entrega_periodo' => $tiempo_entrega_periodo->value,
            'tiempo_entrega_dias' => $tiempo_entrega_dias,
            //
            'cantidad' => $cantidad,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_base' => $cantidad_base,
            //
            'precio_unitario' => $precio_unitario,
            'precio_unitario_base' => $precio_unitario_base,
            //
            'comentario' => $comentario,
            //
            'estado' => $estado->value,
        ]);
    }



    public static function get_detalles(
        ?int $id_detalle = null,
        null|int|array $ids_cotizaciones = null
    ) {
        $sql = '
        SELECT
            ctd.id AS id_cotizacion_detalle,
            ctd.id_cotizacion,
            ctd.id_comparativo_detalle,
            -- 
            -- info del almacen para el que van destinados los productos
            ctd.id_almacen_recepcionista,
            alm.nombre AS almacen_recepcionista,
            alm.es_principal para_un_almacen_principal,
            -- 
            -- info para la recepcion
            ctd.tipo_despacho, -- recojo o envio
            ctd.lugar_recojo,
            -- 
            -- info del plazo de entrega
            ctd.tiempo_entrega, -- 2
            ctd.tiempo_entrega_periodo, -- semanas
            ctd.tiempo_entrega_dias, -- 14 dias
            -- 
            -- informacion del producto
            cpd.id_producto,
            prd.nombre as producto,
            prd.es_fiscalizado,
            prd.es_perecible,
            -- 
            -- unidad de medida de la cotizacion
            ctd.id_unidad_medida AS id_unidad_medida_ctz,
            und_c.nombre AS unidad_medida_ctz,
            und_c.abreviatura AS unidad_medida_ctz_abv,
            -- 
            -- unidad de medida del producto
            prd.id_unidad_medida_base,
            und_b.nombre AS unidad_medida_base,
            und_b.abreviatura AS unidad_medida_base_abv,
            -- 
            ctd.cantidad, -- segun la unidad de la cotizacion
            ctd.contenido_por_presentacion, -- cuantas unidades base del producto hay en una unidad de la cotizacion
            ctd.cantidad_base, -- segun la unidad base del producto
            -- 
            ctd.precio_unitario,
            ctd.precio_unitario_base,
            -- 
            ctd.comentario,
            ctd.estado
        FROM
            cotizacion_detalle ctd
        INNER JOIN almacen alm ON
            alm.id = ctd.id_almacen_recepcionista
        INNER JOIN unidad_medida und_c ON
            und_c.id = ctd.id_unidad_medida
        INNER JOIN comparativo_detalle cpd ON
            cpd.id = ctd.id_comparativo_detalle
        INNER JOIN producto prd ON
            prd.id = cpd.id_producto
        INNER JOIN unidad_medida und_b ON
            und_b.id = prd.id_unidad_medida_base
        WHERE 1 = 1 
        ';

        $params = [];

        if ($id_detalle !== null) {
            $sql .= ' AND ctd.id = ?';
            $params[] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        // Búsqueda por colección de cotizaciones (Retorna un array)
        if ($ids_cotizaciones !== null) {
            if (is_array($ids_cotizaciones)) {
                // array_values garantiza que los índices no rompan el merge
                $placeholders = implode(',', array_fill(0, count($ids_cotizaciones), '?'));
                $sql .= " AND ctd.id_cotizacion IN ({$placeholders})";
                $params = array_merge($params, array_values($ids_cotizaciones));
            } else {
                $sql .= ' AND ctd.id_cotizacion = ?';
                $params[] = $ids_cotizaciones;
            }
        }

        $sql .= ' ORDER BY prd.nombre ASC, ctd.id ASC';

        return DB::select($sql, $params);
    }
}
