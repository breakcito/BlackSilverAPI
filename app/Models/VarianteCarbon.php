<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla de auto-relacion para tipo_carbon:
 * - id_tipo_carbon: el tipo "padre" (los que reciben variantes).
 * - id_tipo_variante: el tipo "hijo" usado como variante (FK logica a tipo_carbon.id).
 * UNIQUE(id_tipo_carbon, id_tipo_variante) evita duplicados.
 */
class VarianteCarbon extends Model
{
    protected $table = 'variante_carbon';

    public $timestamps = false;

    protected $fillable = [
        'id_tipo_carbon',
        'id_tipo_variante',
    ];
}