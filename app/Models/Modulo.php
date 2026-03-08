<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Modulo extends Model
{
    protected $table = 'modulo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'path',
        'estado',
    ];
}
