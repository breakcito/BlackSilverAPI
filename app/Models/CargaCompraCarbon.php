<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que representa cada vehiculo/carga con carbon que 
 * va entregando/enviando el proveedor. Apenas se registra, si
 * esta carga llego a un almacen de la empresa, entonces se
 * actualiza el stock de carbon de ese tipo de carbon de ese almacen
 * y por ende hace un registro en el kardex de carbon por ingreso
 */
class CargaCompraCarbon extends Model
{
    protected $table = 'carga_compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_compra_carbon',
        'id_empleado_registro', // quien registra esta carga
        'id_tipo_carbon', // el tipo de carbon que viene en el carro
        'id_lugar_extraccion', // el lugar de donde el proveedor extrajo ese carbon
        'id_almacen_proveedor_recojo', // si es recojo, de que almacen del proveedor se recoge
        // lugar al que esta llegando la carga, a un almacen de la empresa o de algun cliente?
        'id_almacen_empresa_llegada', // a que almacen de la empresa esta ingresando
        'id_almacen_cliente_llegada', // aveces la carga llegara directamente al almacen de un cliente
        //
        'id_tarifa_carbon', // la tarifca de costos aplicada en su valorizacion segun el porcentaje de ceniza de ese tipo de carbon
        //
        'id_transportista', // opcional - solo es obligatorio si se va a pagar el flete
        // a que comprobante (si la compra aplica para igv) o pago directo (si la compra NO aplica igv) esta sujeta esta carga
        'id_comprobante_transporte_carbon', // si se pagara el flete de la carga
        'id_comprobante_compra_carbon', // si la compra aplica igv, cada carga o grupo de cargas estaran en un comprobante
        'id_pago_compra_carbon', // si la compra NO aplica igv y solo se hacen pagos directos de cada carga o en grupo de forma directa sin tener un comprobante
        //
        'tipo_despacho', // Recojo/Envio
        //
        'placa', // del vehiculo en el que llego
        'fecha_hora_ingreso', // fecha y hora en la que ingreso el vehiculo al almacen de llegada
        // guias que trajo el vehiculo
        'guia_remitente',
        'guia_transportista', // opcional
        //
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
        //
        'log_cambios', // json
        'created_at',
        'estado', // En Liquidación (apenas se registra) / Pagado (cuando se paga) / Anulado 
    ];
}