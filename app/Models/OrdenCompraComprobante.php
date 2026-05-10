<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Representaria el/los comprobantes de una OC que entrega el proveedor a la empresa. 
 * Esta puede agrupar una, algunas o todas las recepciones de la OC
 * 
 * Se guarda el tipo de cambio segun la fecha que fue emitido el comprobante
 * El monto representa lo que se va a pagar segun la moneda acordada en la Cotizacion y Orden de compra
 * El saldo IGV en soles es el monto calculado segun el monto del comprobante, si la OC incluye o no el IGV y su valor de tipo de cambio de compra solo si su moneda no es soles de la fecha de emision del comprobante. Este monto puede ser usado a favor por la empresa  cuando pague sus impuestos a SUNAT ya que seran restados del total a pagar.
 */
class OrdenCompraComprobante extends Model
{
    protected $table = 'orden_compra_comprobante';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro',
        'id_orden_compra',
        //
        'tipo_comprobante', // enum TipoComprobante
        'serie',
        'numero',
        'fecha_emision',
        'observacion',
        'evidencias',
        //
        'moneda', // enum Moneda
        'tipo_cambio_venta_aplicado',
        'es_auditable',
        //
        'total_antes_igv',
        'total_antes_igv_soles',
        'incluye_igv',
        'porcentaje_igv',
        'monto_igv',
        'monto_igv_soles',
        'total_despues_igv',
        'total_despues_igv_soles',
        //
        'created_at',
        'estado' // enum EstadoOCComprobante
    ];

    public static function get_comprobantes(?int $id_comprobante = null, ?int $id_orden_compra = null)
    {
        $sql = '
        SELECT
            c.id as id_comprobante,
            c.id_orden_compra,
            --
            c.tipo_comprobante,
            c.serie,
            c.numero,
            c.fecha_emision,
            c.observacion,
            c.evidencias,
            --
            c.moneda,
            c.tipo_cambio_venta_aplicado,
            c.es_auditable,
            --
            c.total_antes_igv,
            c.total_antes_igv_soles,
            c.incluye_igv,
            c.porcentaje_igv,
            c.monto_igv,
            c.monto_igv_soles,
            c.total_despues_igv,
            c.total_despues_igv_soles,
            --
            -- Datos del registro
            c.id_empleado_registro,
            CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
            --
            c.created_at,
            c.estado
        FROM
            orden_compra_comprobante c
        INNER JOIN empleado e ON e.id = c.id_empleado_registro
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_comprobante !== null) {
            $sql .= " AND c.id = :id_comprobante";
            $params['id_comprobante'] = $id_comprobante;
            return DB::selectOne($sql, $params);
        }

        if ($id_orden_compra !== null) {
            $sql .= " AND c.id_orden_compra = :id_orden_compra";
            $params['id_orden_compra'] = $id_orden_compra;
        }

        $sql .= " ORDER BY c.created_at DESC";

        return DB::select($sql, $params);
    }
}
