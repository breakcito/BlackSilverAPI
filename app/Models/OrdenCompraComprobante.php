<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
