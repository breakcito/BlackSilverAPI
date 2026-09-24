<?php

namespace App\Models;

use App\Shared\Enums\_Generic\TipoTurno;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntregaDetalleConsumo extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle_consumo';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega_detalle', // el detalle de la entrega (NULL en consumo directo)
        'id_activo_fijo_consumidor',
        'id_labor_destino',
        'id_empleado_registro',
        'id_mantenimiento',
        'id_lote_mineral',
        'para_mantenimiento',
        'para_produccion',
        'cantidad_base_consumida',
        'fecha_hora_consumo',
        'comentario_consumo',
        'created_at',
        'estado',
        // Nuevos campos para consumo directo desde Control de Uso
        'uuid_control_uso_activo', // uuid que agrupa varios registros de la tabla de control_uso_activo
        'id_producto',
        'id_almacen',
        'id_lote_producto',
        'id_unidad_medida',
        'contenido_por_presentacion',
        'cantidad_consumo',
        'cantidad_base',
        'es_consumo_directo',
        //
        'tipo_turno', // Dia/Noche
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
        ?int $id_labor_destino = null,
        ?int $id_mantenimiento = null,
        ?int $id_lote_mineral = null,
        bool $para_mantenimiento = false,
        bool $para_produccion = false,
        ?TipoTurno $tipo_turno = null
    ): int {
        return self::insertGetId([
            'id_requerimiento_almacen_entrega_detalle' => $id_requerimiento_almacen_entrega_detalle,
            'id_activo_fijo_consumidor' => $id_activo_fijo_consumidor,
            'id_labor_destino' => $id_labor_destino,
            'id_empleado_registro' => $id_empleado_registro,
            'id_mantenimiento' => $id_mantenimiento,
            'id_lote_mineral' => $id_lote_mineral,
            'para_mantenimiento' => $para_mantenimiento,
            'para_produccion' => $para_produccion,
            'cantidad_base_consumida' => $cantidad_base_consumida,
            'fecha_hora_consumo' => $fecha_hora_consumo,
            'comentario_consumo' => $comentario_consumo,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado->value,
            'tipo_turno' => $tipo_turno?->value,
        ]);
    }

    /**
     * Registrar un consumo DIRECTO desde Control de Uso (sin requerimiento previo).
     * Inserta en `requerimiento_almacen_entrega_detalle_consumo` con
     * `es_consumo_directo=1` y `uuid_control_uso_activo` para vincular el
     * consumo al GRUPO (uuid_grupo) de control de uso que lo origina.
     *
     * Ya NO se persiste `id_control_uso_activo`: el consumo pertenece al
     * grupo UUID completo (un "Registrar Control por Horometro" puede
     * tener varios bloques y los consumos no son propios de un unico
     * bloque, sino del grupo entero).
     */
    public static function crear_consumo_directo(
        int $id_empleado_registro,
        int $id_activo_fijo_consumidor,
        ?int $id_lote_mineral,
        ?int $id_labor_destino,
        bool $para_mantenimiento,
        bool $para_produccion,
        float $cantidad_base_consumida,
        ?string $comentario_consumo,
        string $uuid_control_uso_activo,
        int $id_producto,
        int $id_almacen,
        int $id_lote_producto,
        int $id_unidad_medida,
        float $contenido_por_presentacion,
        float $cantidad_consumo,
        float $cantidad_base,
        EstadoConsumoDetalleEntregaReq $estado,
        ?TipoTurno $tipo_turno = null
    ): int {
        return self::insertGetId([
            // FK legacy (NULL en consumo directo)
            'id_requerimiento_almacen_entrega_detalle' => null,
            // Datos del consumo
            'id_activo_fijo_consumidor' => $id_activo_fijo_consumidor,
            'id_labor_destino' => $id_labor_destino,
            'id_empleado_registro' => $id_empleado_registro,
            'id_mantenimiento' => null,
            'id_lote_mineral' => $id_lote_mineral,
            // La asociacion con el grupo de control de uso se hace solo
            // por `uuid_control_uso_activo` (== `control_uso_activo.uuid_grupo`).
            // `id_control_uso_activo` queda NULL: el consumo representa al
            // grupo, no a un item especifico.
            'id_control_uso_activo' => null,
            'para_mantenimiento' => $para_mantenimiento,
            'para_produccion' => $para_produccion,
            'cantidad_base_consumida' => $cantidad_base_consumida,
            'fecha_hora_consumo' => now()->toDateTimeString(),
            'comentario_consumo' => $comentario_consumo,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado->value,
            // Campos nuevos para consumo directo
            'uuid_control_uso_activo' => $uuid_control_uso_activo,
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen,
            'id_lote_producto' => $id_lote_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_consumo' => $cantidad_consumo,
            'cantidad_base' => $cantidad_base,
            'es_consumo_directo' => true,
            'tipo_turno' => $tipo_turno?->value,
        ]);
    }

    /**
     * Actualizar el turno de un consumo.
     */
    public static function actualizar_turno(int $id_consumo, TipoTurno $tipo_turno): bool
    {
        return self::where('id', $id_consumo)->update([
            'tipo_turno' => $tipo_turno->value,
        ]) > 0;
    }
}
