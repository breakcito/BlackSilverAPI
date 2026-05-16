<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a los activos con los que cuenta la empresa, como
 * por ejemplo: vehiculos, maquinarias, equipos electrogenos, compresoras, etc
 */
class ActivoFijo extends Model
{
    protected $table = 'activo_fijo';

    public $timestamps = false;

    protected $fillable = [
        'id_producto', // que producto es este activo
        // En que lugar se encuentra, solo una de ellas debe de tener valor
        'id_almacen', // opcional - en que almacen se encuentra almacenado este activo
        'id_mina', // opcional - en que mina se encuentra siendo usado este activo
        'id_marca', // de que marca - opcional
        //
        'codigo', // opcional - lo pone el usuario
        'correlativo', // como prefijo utiliza el que ha sido dado en el modulo de productos
        'numero_correlativo',
        //
        'numero_serie', // identificador del fabricante
        'modelo', // nombre del modelo
        'yearcito_modelo', // año del modelo
        'descripcion', // opcional
        // 
        'especificaciones', // Columna JSON para almacenar especificaciones dinámicas
        // [{"clave": "", "valor": ""} ... ]
        //
        'fecha_hora_ingreso', // fecha en la que el activo ingresa a la empresa
        'created_at', // fecha en la que se registro en el sistema
        'estado' // EstadoActivoFijo - En Uso, En Mantenimiento, En Almacen, Dado de Baja
    ];
}
