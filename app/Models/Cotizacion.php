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
        //
        'correlativo',
        'numero_correlativo',
        //
        'observacion',
        'fecha_hora_cotizacion',
        //
        'metodo_pago', // Contado / Credito
        'fecha_vencimiento_pago', // Solo cuando es a credito
        'moneda', // Soles o Dolares
        //
        'costo_flete',
        'otros_gastos',
        // 
        'total_antes_igv',
        'incluye_igv',
        'porcentaje_igv',
        'monto_igv',
        'total_despues_igv',
        //
        'evidencias',
        //
        'estado',
        'created_at',
    ];
}
