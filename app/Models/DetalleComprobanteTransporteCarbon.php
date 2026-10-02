<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que permite agrupar cada carga de la compra de carbon
 * a la que se le debe pagar el flete y es del mismo transportista
 * en un solo comprobante que este entrega
 */
class DetalleComprobanteTransporteCarbon extends Model
{
    protected $table = 'detalle_comprobante_transporte_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_comprobante_transporte_carbon',
        'id_detalle_compra_carbon',
    ];
}
