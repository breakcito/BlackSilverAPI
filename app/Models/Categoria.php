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
        'clasificacion_bien', // Suministro, Materiales, Activo Fijo
        'es_consumible', // si es true, significa que se podra indicar qué otras categorias consumen esta categoria
        'es_auditable', // bool que ayuda a saber si los productos de esa categoria con auditables para ocultarlos
        'para_cocina',
        'para_mina',
        'estado',
    ];
}
