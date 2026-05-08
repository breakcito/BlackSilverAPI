<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaborContratista extends Model
{
    protected $table = 'labor_contratista';

    public $timestamps = false;

    protected $fillable = [
        'id_contratista',
        'id_labor',
    ];
}
