<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que ayuda a identificar:
// - A que minas abastece un almacen
// - Que almacenes abastecen una mina
class AlmacenMina extends Model
{
    protected $table = 'almacen_mina';
    public $timestamps = false;
    protected $fillable = [
        'id_almacen',
        'id_mina',
    ];
}
