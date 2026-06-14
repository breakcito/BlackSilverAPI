<?php

namespace App\Models;

use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntregaDetalleConsumo extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle_consumo';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega_detalle', // el detalle de la entrega
        'id_activo_fijo_consumidor', // que activo fijo esta consumiendo lo entregado 
        'id_labor_destino', // a que labor de esa u otra mina del requerimiento esta dirigiedo lo solicitado
        'id_empleado_registro', // quien registro el consumo
        'id_mantenimiento', // en caso haya sido usado para un mantenimiento
        'id_lote_mineral', // si es para produccion, debera indicar para que lote va
        // O es uno u otro - aqui vuelve a confirmar si es para mantenimiento, sino es asi , es para produccion
        'para_mantenimiento', // bool
        'para_produccion', // bool
        //
        'cantidad_base_consumida', // cantidad consumida en base a la unidad base
        'fecha_hora_consumo', // fecha y hora del consumo
        'comentario_consumo', // comentario del consumo
        //
        'created_at', // fecha y hora del registro en el sistema
        'estado', // Consumo Parcial / Consumo Total
    ];

    /**
     * Registrar un nuevo consumo en la base de datos.
     */
    public static function crear_consumo(
        int $id_requerimiento_almacen_entrega_detalle,
        int $id_empleado_registro,
        float $cantidad_base_consumida,
        string $fecha_hora_consumo,
        ?string $comentario_consumo,
        EstadoConsumoDetalleEntregaReq $estado,
        ?int $id_activo_fijo_consumidor = null,
        ?int $id_labor_destino = null
    ): int {
        return self::insertGetId([
            'id_requerimiento_almacen_entrega_detalle' => $id_requerimiento_almacen_entrega_detalle,
            'id_activo_fijo_consumidor' => $id_activo_fijo_consumidor,
            'id_labor_destino' => $id_labor_destino,
            'id_empleado_registro' => $id_empleado_registro,
            'cantidad_base_consumida' => $cantidad_base_consumida,
            'fecha_hora_consumo' => $fecha_hora_consumo,
            'comentario_consumo' => $comentario_consumo,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado->value,
        ]);
    }
}
