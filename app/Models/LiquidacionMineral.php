<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidacionMineral extends Model
{
    protected $table = 'liquidacion_mineral';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_cliente',
        //
        'codigo_liquidacion',
        'elemento_quimico', // enum: Oro | Plata
        'fecha_liquidacion',
        //
        'peso_humedo',
        'tara',
        'peso_neto',
        //
        //
        'ley_elemento', // en oz
        'porcentaje_humedad',
        //
        'peso_seco',
        'merma',
        //
        'porcentaje_recuperacion',
        'maquila',
        'penalidad',
        //
        'valor_por_tonelada',
        //
        'subtotal',
        'igv',
        'total',
        'detraccion',
        'neto',
        //
        'observaciones',
        'created_at',
        'estado', // enum: Borrador | Aprobada | Pagada
    ];
}
