<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que representa la compra de carbon, agrupando
 * una o varias cargas/carros
 */
class CompraCarbon extends Model
{
    protected $table = 'compra_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa', // la empresa que compra
        'id_proveedor', // el proveedor al que se le va a comprar
        //
        'id_empleado_registro', // quien registra
        'id_empleado_anula', // quien anula
        //
        'id_tipo_carbon_prometido', //  lo que dice el proveedor que te va a traer
        'id_tarifa_carbon', // la tarifa de costos aplicada, por defecto se toma la tarifa mas alta segun el tipo de carbon
        //
        'correlativo', // <numero de correlativo>-<año>
        'numero_correlativo', // se reinicia al año
        //
        // si NO aplica igv, el pago de esta compra se hará sin asociarlo a ningun 
        // comprobante. Si es TRUE, entonces los pagos estaran asociados a 
        // un comprobante
        'aplica_igv', // bool 
        'porcentaje_igv', // 0 si no aplica igv, si si aplica por defecto 18 pero el usuario lo puede cambiar
        //
        'toneladas_prometidas', // cantidad de toneladas que dice el proveedor que va a traer
        'precio_unitario_cotizado', // se autocompleta con la tarifa pero el usuario puede modificar
        'total_cotizado', // precio por tonelada * cantidad de toneladas prometidas
        'monto_igv_cotizado', // representa el 18% del total cotizado si aplica igv. Si no aplica IGV este es 0.
        //
        'log_cambios', // json, para guardar las ediciones de esta compra
        'created_at', // cuando se registro en el sistema
        'fecha_hora_anulacion',
        'estado' // Preliminar / En Liquidacion (cuando llega la primera carga) / Pagado (cuando se pagaron todas las cargas) / Anulado 
    ];
}