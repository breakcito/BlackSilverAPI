<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportistaMineral extends Model
{
    protected $table = 'transportista_mineral';

    public $timestamps = false;

    protected $fillable = [
        'ruc',
        'razon_social',
    ];
}
