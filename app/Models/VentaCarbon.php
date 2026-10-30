<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que registra las ventas de carbon a nivel de cabecera
 */
class VentaCarbon extends Model
{
    protected $table = 'venta_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_registro',
        'id_empresa', // cupper por default
        'id_cliente', // a quien se le factura realmente
        'id_cliente_destino', // el lugar/empresa a la que llega realmente la carga
        'id_almacen_empresa_salida', // de que almacen de carbon va a salir 
        'id_almacen_cliente_llegada', // a donde va a llegar
        'correlativo', // VC-
        'numero_correlativo',
        'porcentaje_igv', // % de IGV que se aplica a esta venta
        'porcentaje_detraccion', // % de detraccion que se aplica a esta venta
        'created_at',
    ];
}
