<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categoria';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_producto', // Bien o Servicio
        'clasificacion_bien', // Suministro, Material, Activo Fijo
        'es_consumible', // si es true, significa que se podra indicar qué otras categorias consumen esta categoria
        'es_auditable', // bool que ayuda a saber si los productos de esa categoria con auditables para ocultarlos
        //
        // Si es activo fijo
        // al registar un activo fijo de de esta categoria sera necesario ingresar datos propios de un vehiculo - para 
        // consultas y saber los vehiculos que tiene el consorcio
        'para_transporte', 
        'control_por_odometro', // util para saber si llevara un control por kilometraje recorrido - para el modulo de Uso
        'control_por_horometro', // util para saber si llevara un control por horas de trabajo - para el modulo de Uso
        'control_por_vueltas', // util para saber si llevara un control por numero de vueltas - para el modulo de Uso
        //
        // Destinos de uso
        'para_cocina', // indica que los productos de esta categoria seran para cocina
        'para_mina', // indica que los productos de esta categoria seran para mina
        // 
        'estado', // Estado Base
    ];
}
