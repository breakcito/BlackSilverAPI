<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalle';
    
    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_comparativo_detalle',
        'id_unidad_medida',
        'cantidad',
        'contenido_por_presentacion',
        'cantidad_base',
        'precio_unitario',
        'precio_unitario_base',
        'comentario',
    ];
}
