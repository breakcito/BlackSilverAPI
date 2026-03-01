<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que ayuda a identificar:
// - Que minas administra una empresa
// - Que empresas administran una mina
class EmpresaMina extends Model
{
    protected $table = 'empresa_mina';
    public $timestamps = false;
    protected $fillable = [
        'id_mina',
        'id_empresa',
    ];
}
