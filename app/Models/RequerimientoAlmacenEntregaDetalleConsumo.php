<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntregaDetalleConsumo extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle_consumo';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega_detalle', // el detalle de la entrega
        'id_activo_fijo_consumidor', // que activo fijo esta consumiendo lo entregado 
        'id_labor_destino', // a que labor de esa u otra mina del requerimiento esta dirigiedo lo solicitado
        'id_empleado_registro', // quien registro el consumo
        //
        'cantidad_base_consumida', // cantidad consumida en base a la unidad base
        'fecha_hora_consumo', // fecha y hora del consumo
        'comentario_consumo', // comentario del consumo
        //
        'created_at', // fecha y hora del registro en el sistema
        'estado', // Consumo Parcial / Consumo Total
    ];
}
