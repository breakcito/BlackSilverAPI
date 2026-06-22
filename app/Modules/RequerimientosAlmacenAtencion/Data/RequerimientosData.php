<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenLabor;
use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Helpers\CorrelativoHelper;

class RequerimientosData
{

    /**
     * Obtiene los requerimientos de almacen por atender/atendidos
     */
    public static function get_resumen_requerimientos(
        ?int $id_almacen = null,
        ?string $mes = null,
        ?string $yearcito = null,
        ?int $id_requerimiento = null
    ) {
        return RequerimientoAlmacen::get_requerimientos(
            id_almacen_destino: $id_almacen,
            mes: $mes,
            yearcito: $yearcito,
            id_requerimiento: $id_requerimiento
        );
    }

    public static function get_requerimiento_by_id(int $id_requerimiento)
    {
        return self::get_resumen_requerimientos(id_requerimiento: $id_requerimiento);
    }

    /**
     * Obtiene las labores asociadas a un requerimiento de almacen
     */
    public static function get_labores_by_requerimiento(int $id_requerimiento)
    {
        return Labor::get_labores_by_requerimiento(id_requerimiento: $id_requerimiento);
    }

    /**
     * Obtener la mina asociada a un requerimiento (destino del activo fijo entregado)
     */
    public static function get_id_mina_by_requerimiento(int $id_requerimiento): ?int
    {
        $row = RequerimientoAlmacen::select('id_mina')
            ->where('id', $id_requerimiento)
            ->first();

        return $row ? (int) $row->id_mina : null;
    }

    /**
     * Obtener almacen de destino de un requerimiento de almacen
     */
    public static function get_almacen_destino_by_requerimiento(int $id_requerimiento)
    {
        return RequerimientoAlmacen::select('id_almacen_destino')
            ->where('id', $id_requerimiento)
            ->first();
    }

    public static function update_requerimiento_estado(int $id_requerimiento, string $estado)
    {
        return RequerimientoAlmacen::where('id', $id_requerimiento)
            ->update([
                'estado' => $estado
            ]);
    }

    public static function get_correlativo_by_requerimiento(int $id_requerimiento)
    {
        return RequerimientoAlmacen::select('correlativo')
            ->where('id', $id_requerimiento)
            ->first();
    }

    /**
     * Consultas utiles para el registro de un requerimiento
     */

    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'requerimiento_almacen',
            prefijo: 'REQ'
        );
    }


    public static function crear_requerimiento(
        ?int $id_empleado_solicitante,
        int $id_empleado_registro,
        ?int $id_mina,
        int $id_almacen_destino,
        string $correlativo,
        int $numero_correlativo,
        bool $es_auditable,
        Premura $premura,
        ?string $observacion = null,
        ?string $fecha_entrega_requerida = null,
        ?array $evidencias = null
    ) {
        return RequerimientoAlmacen::insertGetId([
            'id_empleado_solicitante' => $id_empleado_solicitante,
            'id_empleado_registro' => $id_empleado_registro,
            'id_mina' => $id_mina,
            'id_almacen_destino' => $id_almacen_destino,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'es_auditable' => $es_auditable,
            'premura' => $premura->value,
            'observacion' => $observacion,
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'created_at' => now(),
            'estado' => EstadoRequerimiento::Generado->value,
        ]);
    }

    public static function guardar_evidencias(array $evidencias)
    {
        return ArchivoHelper::guardarArchivos('requerimientos_almacen', $evidencias);
    }

    /**
     * Asocia una labor al requerimiento
     */
    public static function asignar_labor(int $id_requerimiento, int $id_labor)
    {
        return RequerimientoAlmacenLabor::insertGetId([
            'id_requerimiento' => $id_requerimiento,
            'id_labor' => $id_labor,
        ]);
    }
}
