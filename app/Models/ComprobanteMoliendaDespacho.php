<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteMoliendaDespacho extends Model
{
    protected $table = 'comprobante_molienda_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'codigo_comprobante',
        'fecha_emision',
        'con_detraccion',
        'porcentaje_detraccion', // 12%
        'subtotal',
        'igv',
        'total',
        'monto_detraccion',
        'neto_a_pagar',
        'avance_detraccion',
        'avance_neto',
        'evidencias',
        'estado',
    ];
}
