<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenLabor;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

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

    public static function get_nuevo_correlativo(int $id_almacen_destino)
    {
        return CorrelativoHelper::generar(
            tabla: 'requerimiento_almacen',
            prefijo: 'REQ',
            filtros: ['id_almacen_destino' => $id_almacen_destino]
        );
    }

    /**
     * Obtiene la lista de minas a las que abastece el almacen elegido
     */
    public static function get_minas_by_almacen(int $id_almacen)
    {
        $sql = '
        SELECT DISTINCT
            mn.id AS id_mina,
            mn.nombre
        FROM
            mina mn
        INNER JOIN almacen_mina ami ON
            ami.id_mina = mn.id
        WHERE
            ami.id_almacen = :id_almacen
        ORDER BY mn.nombre ASC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }

    /**
     * Obtiene los responsables activos de una mina
     */
    public static function get_responsables_by_mina(int $id_mina)
    {
        $sql = '
        SELECT DISTINCT
            c.id AS id_contratista,
            CONCAT(c.nombre, " ", c.apellido) AS nombre_completo
        FROM
            contratista c
        INNER JOIN responsable_mina res ON
            res.id_contratista = c.id
        WHERE
            res.id_mina = :id_mina AND
            res.estado = "Activo" AND
            res.fecha_fin IS NULL
        ORDER BY nombre_completo ASC
        ';

        return DB::select($sql, ['id_mina' => $id_mina]);
    }

    /**
     * Obtiene las labores de una mina
     */
    public static function get_labores(int $id_mina)
    {
        $sql = '
        SELECT
            lab.id AS id_labor,
            lab.nombre,
            lab.correlativo
        FROM
            labor lab
        WHERE
            lab.estado = "Activo" AND
            lab.id_mina = :id_mina
        ORDER BY lab.nombre ASC
        ';

        return DB::select($sql, ['id_mina' => $id_mina]);
    }

    public static function crear_requerimiento(
        int $id_contratista_solicitante,
        int $id_empleado_registro,
        int $id_mina,
        int $id_almacen_destino,
        string $correlativo,
        int $numero_correlativo,
        string $premura,
        ?string $observacion,
        string $fecha_entrega_requerida,
        ?array $evidencias = null
    ) {
        return RequerimientoAlmacen::insertGetId([
            'id_contratista_solicitante' => $id_contratista_solicitante,
            'id_empleado_registro' => $id_empleado_registro,
            'id_mina' => $id_mina,
            'id_almacen_destino' => $id_almacen_destino,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'premura' => $premura,
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
