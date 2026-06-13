<?php

namespace App\Modules\RequerimientosAlmacen\Data;

use App\Models\Labor;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenLabor;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class RequerimientosData
{
    /**
     * Obtiene los requerimientos de almacen hechos por el usuario
     */
    public static function get_resumen_requerimientos(
        ?int $id_requerimiento = null,
        ?int $id_contratista_solicitante = null,
        ?string $mes = null,
        ?string $yearcito = null
    ) {
        return RequerimientoAlmacen::get_requerimientos(
            id_requerimiento: $id_requerimiento,
            id_contratista_solicitante: $id_contratista_solicitante,
            mes: $mes,
            yearcito: $yearcito
        );
    }

    public static function get_requerimiento_by_id(int $id_requerimiento)
    {
        return self::get_resumen_requerimientos(id_requerimiento: $id_requerimiento);
    }

    /**
     * Obtiene las labores asociadas a un requerimiento de almacen
     */
    public static function get_labores_by_requerimiento(?int $id_requerimiento = null, ?int $id_enlace = null)
    {
        return Labor::get_labores_by_requerimiento($id_requerimiento, $id_enlace);
    }

    public static function get_requerimiento_labor_by_id(int $id_enlace)
    {
        return self::get_labores_by_requerimiento(id_enlace: $id_enlace);
    }

    /**
     * Consultas utiles para el registro de un requerimiento
     */

    /**
     * Obtener el nuevo correlativo para un requerimiento en
     * base al almacen de destino
     */
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'requerimiento_almacen',
            prefijo: 'REQ',
        );
    }

    /**
     * Obtiene la lista de minas en las que el empleado logueado es responsable
     */
    /**
     * Obtiene la lista de minas activas para que el administrador pueda solicitar
     */
    public static function get_minas()
    {
        return DB::select('
            SELECT id AS id_mina, nombre
            FROM mina
            WHERE estado = "Activo"
            ORDER BY nombre ASC
        ');
    }

    /**
     * Obtiene la lista de almacenes que abastecen a la mina elegida
     */
    public static function get_almacenes_by_mina(int $id_mina)
    {
        $sql = '
        SELECT DISTINCT
            al.id AS id_almacen,
            al.nombre
        FROM
            almacen al
        INNER JOIN almacen_mina ami ON
            ami.id_almacen = al.id
        WHERE
            al.es_principal != 1 AND
            ami.id_mina = :id_mina
        ORDER BY al.nombre ASC
        ';

        $params = [
            'id_mina' => $id_mina,
        ];

        return DB::select($sql, $params);
    }

    /**
     * Obtiene la lista de labores que tiene una mina
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
        ORDER BY
            lab.nombre ASC
        ';

        $params = [
            'id_mina' => $id_mina,
        ];

        return DB::select($sql, $params);
    }

    /**
     * Crear un nuevo requerimiento de almacén.
     */
    public static function crear_requerimiento(
        int $id_contratista_solicitante,
        int $id_mina,
        int $id_almacen_destino,
        string $correlativo,
        int $numero_correlativo,
        string $premura,
        ?string $observacion,
        string $fecha_entrega_requerida
    ) {
        return RequerimientoAlmacen::insertGetId([
            'id_contratista_solicitante' => $id_contratista_solicitante,
            'id_mina' => $id_mina,
            'id_almacen_destino' => $id_almacen_destino,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'premura' => $premura,
            'observacion' => $observacion,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'created_at' => now(),
            'estado' => EstadoRequerimiento::Generado->value,
        ]);
    }

    /**
     * Asocia una labor al requerimiento en base a la mina de este requerimiento
     */
    public static function asignar_labor(int $id_requerimiento, int $id_labor)
    {
        return RequerimientoAlmacenLabor::insertGetId([
            'id_requerimiento' => $id_requerimiento,
            'id_labor' => $id_labor,
        ]);
    }
}
