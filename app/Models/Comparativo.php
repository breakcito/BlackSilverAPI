<?php

namespace App\Models;

use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;

class Comparativo extends Model
{
    protected $table = 'comparativo';

    public $timestamps = false;

    protected $fillable = [
        'numero_correlativo', // anual
        'created_at'
    ];

    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'comparativo',
            prefijo: '',
            columnaFecha: 'created_at'
        );
    }

    /**
     * Crear el registro maestro del comparativo
     */
    public static function crear_comparativo(int $numero_correlativo): int
    {
        return self::insertGetId([
            'numero_correlativo' => $numero_correlativo,
            'created_at' => now(),
        ]);
    }

}
