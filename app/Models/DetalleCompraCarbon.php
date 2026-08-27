<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que representa cada vehiculo con carbon incluido en una compra
 */
class DetalleCompraCarbon extends Model
{
    protected $table = 'detalle_compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_compra_carbon',
        'id_tipo_carbon',
        'id_transportista', // opcional - solo es obligatorio si se va a pagar el flete
        'id_lugar_extraccion', // el lugar de donde el proveedor extrajo ese carbon
        'id_tarifa_carbon', // la tarifca de costos aplicada en su valorizacion segun el porcentaje de ceniza
        'placa', // del vehiculo en el que llego
        // guias que trajo el vehiculo
        'guia_remitente',
        'guia_transportista', // opcional
        'pagar_flete', //  bool - si es TRUE, debe indicar la empresa de transporte y el costo del flete por tonelada
        'codigo_ticket_balanza', // el codigo emitido al pesar el carro
        'cantidad', // en toneladas, es la cantidad de carbon que trae el carrro
        'porcentaje_ceniza', // cuanto hay de ceniza en la carga
        'porcentaje_humedad', // cuanta humedad trae la carga
        'precio_unitario', // precio por tonelada, se autocompleta segun la tarifa aplicada pero el usuario lo puede modificar
        'costo_flete_por_tonelada', // 0 por defecto, solo se habilita si se va a pagar el flete
        'subtotal_antes_descuento', // cantidad * precio unitario
        'descuento_flete', // cantidad * costo de flete por tonelada
        'subtotal_con_descuento', // subtotal sin descuento - descuento de flete
        'evidencias',
    ];
}