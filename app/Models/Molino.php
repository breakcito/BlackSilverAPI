<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Molino extends Model
{
    protected $table = 'molino';

    public $timestamps = false;

    protected $fillable = [
        'ruc',
        'razon_social',
    ];
}
