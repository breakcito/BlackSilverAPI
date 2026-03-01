<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoFamilia extends Model
{
    protected $table = 'producto_familia';
    public $timestamps = false;
    protected $fillable = [
        'id_producto',
        'id_familia',
    ];
}
