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
        'id_tipo_carbon_estimado', // el que se dice que se esta enviando
        'id_tipo_carbon_confirmado', // el que dice el cliente que le ha llegado 
        'id_vehiculo_carbon',
        'id_comprobante_venta_carbon',
        // DATOS QUE PONE AL SALIR/REGISTRAR
        'calidad', // enum: Baja |Intermedia | Alta
        'segunda_placa', // si el tipo de vehiculo es Tolva
        'fecha_hora_salida',
        // DATOS QUE PONE AL LLEGAR
        'fecha_hora_ingreso',
        'guia_remitente',
        'guia_transportista', // opcional
        'codigo_ticket_balanza',
        'codigo_reporte',
        'observacion',
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
        'cantidad_toneladas',
        'cantidad_toneladas',
        'precio_base',
        'subtotal_sin_descuento', // cantidad x precio unitario
        'descuento', // opcional
        'bono', // opcional
        'subtotal', // subtotal_sin_descuento - descuento + bono
        'evidencias',
        'created_at',
        'estado', // enum En Camino | Llegada Confirmada

    ];
}
