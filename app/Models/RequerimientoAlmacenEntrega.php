<?php

namespace App\Models;

use App\Shared\Enums\EstadoRequerimiento;
use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega'; // entrega_almacen

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_empleado_entrega', // quien entrega los productos
        'id_empleado_recibe', // quien recibe los productos
        //
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias', // json con el formato [{ "path": "...", "nombre": "...", "extension": "..." }]
        //
        'created_at',
        'estado',
    ];

    public static function crear_entrega(
        string $correlativo,
        int $numero_correlativo,
        int $id_usuario_entrega,
        int $id_requerimiento,
        string $fecha_entrega,
        ?string $observacion = null,
        ?string $evidencias = null
    ) {
        return self::insertGetId([
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'id_usuario_entrega' => $id_usuario_entrega,
            'id_requerimiento' => $id_requerimiento,
            'fecha_entrega' => $fecha_entrega,
            'observacion' => $observacion,
            'evidencias' => $evidencias,
            'created_at' => now(),
            'estado' => EstadoRequerimiento::Generada->value,
        ]);
    }
}
