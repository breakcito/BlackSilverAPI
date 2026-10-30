<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que registra las cargas de carbon que se despachan 
 * al cliente para ser vendidas
 */
class CargaVentaCarbon extends Model
{
    protected $table = 'carga_venta_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_venta_carbon',
        'id_empleado_registro',
        'id_tipo_carbon', // el que se esta enviando
        'id_vehiculo_carbon', // se indica cuando llega
        'id_liquidacion_venta_carbon', // se llena cuando se arma la liquidacion
        //
        // DATOS QUE PONE AL SALIR/REGISTRAR, ademas del tipo de carbon
        'calidad', // enum: Baja |Intermedia | Alta
        'fecha_hora_salida',
        //
        // DATOS QUE PONE AL LLEGAR
        'fecha_hora_ingreso',
        'segunda_placa', // si el tipo de vehiculo es Tolva
        'guia_remitente',
        'guia_transportista', // opcional
        'codigo_ticket_balanza',
        'cantidad_toneladas',
        'porcentaje_ceniza',
        'porcentaje_humedad',
        'porcentaje_azufre',
        //
        'analisis_granulometria', // json - solo cuando el tipo de carbon es Tipo A:
        /**
         * {
            "mayor_a_uno”: null,
            "menor_a_uno”: null,
            "un_medio”: null,
            }
         */
        'porcentaje_pizarra',
        'fue_aceptado', // bool
        'observacion',
        //
        // DATOS QUE PONE AL ARMAR LIQUIDACION
        'precio_base', // precio unitario por tonelada pactado con el cliente
        'descuento', // opcional - por defecto es 0
        'bono', // opcional - por defecto es 0
        'precio_materia_prima', // precio_base unitario - descuento + bono
        'subtotal', // precio_materia_prima * cantidad_toneladas
        'monto_igv', // subtotal * porcentaje_igv de la venta
        'total', // subtotal + monto igv
        'monto_detraccion', // total * porcentaje_detraccion de la venta
        'neto_a_pagar', // total - monto de detraccion
        //
        'evidencias',
        'created_at',
        'estado', // enum En Camino | Llegada Confirmada | En Liquidación | Pagado

    ];
}
