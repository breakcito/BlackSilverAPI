<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeccionRol extends Model
{
    protected $table = 'seccion_rol';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'id_rol',
    ];
}
