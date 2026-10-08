<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que representa las variantes del tipo de carbon tamizado.
 * Por ejemplo, si se tamiza una tipo de carbon 'Mixto', puedo obtener
 * otros tipos de carbon como Banca, Cisco o Tipo A. Al registrar esta variante
 * Cuando se registe, la cantidad extraida del tipo de carbon tamizado
 * se sumara al stock del tipo de carbon obtenido como variante, admeas de hacer un 
 * registro en el kardex de carbon
 */
class VarianteTamizajeCarbon extends Model
{
    protected $table = 'variante_tamizaje_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_tamizaje_carbon',
        'id_tipo_variante', // el tipo de carbon resultante del tipo de carbon tamizado
        'cantidad_extraida', //  cuantas toneladas derivaron del tipo de carbon tamizado
        'cambios_log', // JSON  - para guardar cuando editan el peso del tamizaje
    ];
}
