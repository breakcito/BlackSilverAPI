<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laboratorio extends Model
{
    protected $table = 'laboratorio';

    public $timestamps = false;

    protected $fillable = [
        'ruc',
        'razon_social',
    ];
}
