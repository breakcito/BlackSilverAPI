<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla encargada de alojar los datos del personal que no influye
 * en los procesos mas importantes: No registran, no son responsables de algun
 * almacen o mina, etc. Los usos que se le esta dando a esta tabla, son:
 * - Poder elegir la persona encargada de hacer el envio de productos tras
 * una solicitud de reabastecimiento, una entrega por prestamo entre almacenes o 
 * una reposicion por un prestamo entre almacenes
 * 
 * Para el registro, solo sera obligatorio ingresar el nombre
 */
class PersonalExterno extends Model
{
    protected $table = 'personal_externo';
    public $timestamps = false;
    protected $fillable = [
        'id_proveedor', // opcional - en caso sea el personal/trabajador de un proveedor externo
        'es_representante', // 1 cuando es el representante o persona involucrada de un proveedor (modulo carbon)
        //
        'nombre',
        'apellido',
        'dni',
        'estado', // Estado Base
    ];
}
