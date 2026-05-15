<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo que hace referencia a los activos con los que cuenta la empresa, como
 * por ejemplo: vehiculos, maquinarias, equipos electrogenos, compresoras, etc
 * 
 * 
 */
class ActivoFijo extends Model
{
    protected $table = 'activo_fijo';

    public $timestamps = false;

    protected $fillable = [
        'id_producto', // que producto es este activo
        'id_lote_producto', // de que lote es este activo - util para reutilizar todo el proceso ya elaborado para logistica - el lote solo le podra pertenecer a una almacen virtual
        'id_marca', // de que marca - opcional
        // En que lugar se encuentra, solo una de ellas debe de tener valor
        'id_mina', // opcional - en que mina se encuentra siendo usado este activo
        'id_almacen', // opcional - en que almacen se encuentra almacenado este activo
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
        'estado' // Estado Base
    ];
}
