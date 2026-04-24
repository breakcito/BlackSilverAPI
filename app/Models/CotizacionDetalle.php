<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_comparativo_detalle', // tiene la info del producto y si vino de una solicitud de reabastecimiento
        'id_unidad_medida', // Caja
        'id_almacen_recepcionista', // Obligatorio - Es el almacen que deberia recibir esos productos
        //
        'tipo_despacho', // Recojo / Envio
        'lugar_recojo', // Para el recojo es obligatorio
        // tiempos estimados
        'tiempo_entrega', // 2
        'tiempo_entrega_periodo', // Semanas
        'tiempo_entrega_dias', // 14 dias
        // 
        'cantidad', // 2 Cajas
        'contenido_por_presentacion', // 3 Unidades (unidad de medida base del producto) por Caja
        'cantidad_base', // 6 unidades
        //
        'precio_unitario', // S/12 la caja
        'precio_unitario_base', // S/2 por unidad
        //
        'comentario',
        //
        'estado', // Aprovado, Rechazado (cuando se aprueba la cotizacion y no se elige), Pendiente (aun no se aprueba)
    ];
}
