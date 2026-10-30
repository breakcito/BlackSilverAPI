<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidacionVentaCarbon extends Model
{
    protected $table = 'liquidacion_venta_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_venta_carbon', // de que venta es
        'id_empleado_registro', // quien lo registro
        'id_empleado_confirmacion', // quien confirma la liquidacion
        'id_tipo_carbon', // el tipo de carbon de esa liquidacion
        'codigo_orden_compra', // se digita manualmente
        'numero_reporte', // se digita manualmente
        'fecha_aprobacion', // opcional
        'version', // opcional - se digita manualmente
        'evidencias', // opcional - aqui puede subir la liquidacion que le hace llegar el cliente
        //
        'subtotal', // suma de todos los subtotales de todas las cargas
        'monto_igv', // subtotal * porcentaje_igv de la venta
        'total', // subtotal + monto igv
        'monto_detraccion', // total * porcentaje_detraccion de la venta
        'neto_a_pagar', // total - monto de detraccion
        ///
        'esta_confirmado', // bool - 1 cuando se confirme
        'fecha_hora_confirmacion', // fecha/hora de cuando se confirmo
        'created_at', // cuando se registro en el sistema
    ];
}
