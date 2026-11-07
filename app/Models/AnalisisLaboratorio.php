<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisisLaboratorio extends Model
{
    protected $table = 'analisis_laboratorio';

    public $timestamps = false;

    protected $fillable = [
        'id_lote_mineral',
        'id_despacho_mineral',
        'id_comprobante_analisis_despacho',
        'id_cliente',
        //
        'asumido_por', // enum: Propio | Cliente
        'codigo_laboratorio',
        'codigo_cliente',
        'fecha_analisis',
        'evidencias',
        //
        'porcentaje_humedad',
        'ley_oro',
        'ley_plata',
        'ley_cobre',
        'ley_zinc',
        'ley_arsenico',
        //
        'costo_servicio',
        'created_at',
    ];
}
