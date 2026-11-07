<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleteDespacho extends Model
{
    protected $table = 'flete_despacho';

    public $timestamps = false;

    protected $fillable = [
        'id_despacho_mineral',
        'id_transportista_mineral',
        'id_comprobante_flete_despacho',
        //
        'placa',
        'licencia_conductor',
        'guia_remitente',
        'peso_guia',
        // balanza
        'codigo_ticket_balanza',
        'peso_bruto',
        'tara',
        'peso_neto',
        'fecha_hora_pesaje',
        //
        'largo',
        'ancho',
        'alto',
        //
        'evidencias',
    ];
}
