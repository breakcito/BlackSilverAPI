<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comparativo extends Model
{
    protected $table = 'comparativo';
    
    public $timestamps = false;

    protected $fillable = [
        'created_at'
    ];
}
