<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoActivo extends Model
{
    protected $table = 'mantenimiento_activo';

    public $timestamps = false;

    protected $casts = [
        'fecha_hora_mantenimiento' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'id_activo_fijo', // a que activo se le hizo mantenimiento
        'id_empleado_registro', // quien registro el mantenimiento
        // lugar de trabajo, si se hizo en mina o en almacen o en otro lugar
        'id_mina',
        'id_almacen',
        // Involucrados
        'id_empleado_supervisor', // empleado encargado de supervisar el mantenimiento
        'id_proveedor', // opc - proveedor encargado de realizar el mantenimiento
        'id_personal_externo', // personal/trabajador del proveedor - obligatorio si elige un proveedor de tipo juridico
        'id_empleado_ejecutor', // opcional - si el mantenimiento no lo realiza un proveedor, entonces que indique qué empleado realiza el trabajo
        // 
        'fecha_hora_mantenimiento', // autofill en la ui pero el usuario lo puede modificar
        'observacion', // opcional
        'lugar_trabajo', // texto - en caso el mantenimiento no se realizó en mina o en almacen
        'evidencias', // opcional - json
        'serie_factura', // opcional - serie de la factura del proveedor
        'numero_factura', // opcional - numero de la factura del proveedor
        //
        // Costos del mantenimiento
        'costo_mano_obra', // en caso haya sido realizado por un proveedor
        'otros_gastos', // Columna JSON para almacenar otros gastos indicando el concepto y el costo
        // [{"clave": "", "valor": ""} ... ]
        //
        // Log del control acumulado del activo hasta el momento del mantenimiento
        'total_horas',
        'total_kilometros',
        'total_vueltas',
        //
        'created_at',
    ];
}
