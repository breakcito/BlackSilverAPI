<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteAnalisisDespacho extends Model
{
    protected $table = 'comprobante_analisis_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        //
        'codigo_comprobante',
        'fecha_emision',
        'con_detraccion', // bool 
        'porcentaje_detraccion', // 12%
        'subtotal',
        'igv',
        'total',
        'monto_detraccion',
        'neto_a_pagar',
        'avance_detraccion',
        'avance_neto',
        'evidencias',
        'estado', // enum: Pendiente de Pago / En proceso de Pago / Pagado
    ];
}
