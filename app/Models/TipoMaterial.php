<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a los tipos de materiales (Desmonte, Mineral, etc).
 */
class TipoMaterial extends Model
{
    protected $table = 'tipo_material';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
