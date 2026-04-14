<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizacion';

    public $timestamps = false;

    protected $fillable = [
        'id_comparativo',
        'id_proveedor',
        'moneda',
        'correlativo',
        'numero_correlativo',
        'metodo_pago',
        'fecha_vencimiento_pago',
        'total_antes_igv',
        'incluye_igv',
        'porcentaje_igv',
        'monto_igv',
        'total_despues_igv',
        'observacion',
        'evidencias',
        'fecha_hora_cotizacion',
        'estado',
        'created_at',
    ];
}
