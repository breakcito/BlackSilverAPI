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
        'id_producto',
        'id_marca', // opcional

        // util para rastrear de que lote es este activo, con el fin de poder ser reutilizado
        // desde todos los demas modulos de requerimientos, solicitudes, prestamos, cotizaciones, 
        // recepcion de oc's y transferencias
        'id_lote_producto',

        // opcional - en que mina se encuentra siendo usado este activo
        'id_mina',

        'tipo_operacion', // Movil (control por kilometraje) / Estacionario (control por hormetro/horas de uso)

        'codigo', // opcional - lo pone el usuario

        // generado por el sistema
        'correlativo', // como prefijo utiliza el que ha sido dado en el modulo de productos
        'numero_correlativo',

        // bool que ayuda a saber si es auditable para ocultarlo en el modo de auditoria
        'es_auditable',

        // datos dados por el fabricante
        'numero_serie', // identificador del fabricante
        'modelo', // nombre del modelo
        'yearcito_modelo', // año del modelo
        'descripcion', // descripcion interna o del fabricante

        //
        'placa',
        'codigo_mtc',
        'capacidad_tanque', // en galones
        'numero_ejes', // numero de ejes del vehiculo
        'categoria_mtc', // categoria del vehiculo

        // control de horas
        'horas_iniciales', // lectura inicial del horometro/cuenta horas al momento de recepcionar


        'descripcion',
    ];
}
