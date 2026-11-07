<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteFleteDespacho extends Model
{
    protected $table = 'comprobante_flete_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_transportista_mineral',
        'codigo_comprobante',
        'fecha_emision',
        'toneladas_cobradas',
        'precio_por_tonelada',
        'con_detraccion', // bool
        'porcentaje_detraccion', // 4%
        'subtotal',
        'igv',
        'total',
        'monto_detraccion',
        'neto_a_pagar',
        'avance_detraccion',
        'avance_neto',
        'evidencias',
        'created_at',
        'estado', // enum: Pendiente de Pago / En proceso de Pago / Pagado
    ];
}
