<?php

namespace App\Models;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitud;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimiento extends Model
{
    protected $table = 'solicitud_reabastecimiento';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen_solicitante',
        'id_requerimiento_almacen', // null - sirve para saber si fue generado por un requerimiento
        'id_empleado_solicitante',
        //
        'correlativo',
        'numero_correlativo',
        //
        'observacion',
        'premura',
        'fecha_solicitud',
        'fecha_entrega_requerida',
        //
        'es_auditable', // bool que ayuda a saber si es auditable para ocultarlo
        //
        'created_at',
        'estado',
    ];

    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            'solicitud_reabastecimiento',
            'SCR',
        );
    }

    // Funcion helper que ayuda a crear la cabecera de la solicitud
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        Premura $premura,
        bool $es_auditable,
        ?int $id_requerimiento_almacen = null,
        ?string $observacion = null,
        ?string $fecha_entrega_requerida = null,
        ?string $fecha_solicitud = null,
    ) {
        return SolicitudReabastecimiento::insertGetId([
            'id_almacen_solicitante' => $id_almacen_solicitante,
            'id_empleado_solicitante' => $id_empleado_solicitante,
            'id_requerimiento_almacen' => $id_requerimiento_almacen,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'premura' => $premura->value,
            'fecha_solicitud' => $fecha_solicitud,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'es_auditable' => $es_auditable ? 1 : 0,
            'created_at' => now(),
            'estado' => EstadoSolicitud::Generada->value,
        ]);
    }

    /**
     * Obtiene las solicitudes de reabastecimiento por atender/atendidos
     */
    public static function get_solicitudes(
        ?int $id_solicitud = null,
        ?int $id_almacen = null,
        ?int $mes = null,
        ?int $yearcito = null,
        ?int $id_empleado_solicitante = null,
        ?int $id_requerimiento_almacen = null
    ) {
        $sql = '
        SELECT
            scr.id AS id_solicitud,
            scr.correlativo,
            --
            scr.id_almacen_solicitante,
            alm.nombre as almacen_solicitante,
            --
            scr.id_empleado_solicitante,
            CONCAT(emp.nombre, " ", emp.apellido) AS solicitado_por,
            --
            scr.id_requerimiento_almacen,
            ra.correlativo as correlativo_requerimiento,
            --
            scr.observacion,
            scr.premura,
            scr.fecha_solicitud,
            scr.fecha_entrega_requerida,
            --
            scr.es_auditable,
            --
            scr.created_at,
            scr.estado,
            -- Datos del transportista de la ultima entrega registrada (si existe).
            -- Se usa una subconsulta para tomar SOLO la entrega mas reciente
            -- y evitar duplicar filas en el listado (un mismo JOIN con la tabla
            -- de entregas multiplicaria las filas por cada entrega).
            --
            -- NOTA: en MySQL el operador `||` por default es OR logico, NO
            -- concatenacion. Cuando ambos operandos son NULL/`""` el resultado
            -- es el entero `0` en lugar del string vacio esperado. Por eso
            -- aqui usamos la columna cruda (que devuelve NULL en sin match)
            -- y CONCAT_WS para la guia (que ya concatena correctamente).
            (
                SELECT sre.medio_entrega
                FROM solicitud_reabastecimiento_entrega sre
                WHERE sre.id_solicitud_reabastecimiento = scr.id
                ORDER BY sre.id DESC
                LIMIT 1
            ) AS medio_entrega,
            (
                SELECT prov_t.razon_social
                FROM solicitud_reabastecimiento_entrega sre
                LEFT JOIN proveedor prov_t ON prov_t.id = sre.id_proveedor_transporte
                WHERE sre.id_solicitud_reabastecimiento = scr.id
                ORDER BY sre.id DESC
                LIMIT 1
            ) AS proveedor_transporte,
            (
                SELECT age_t.razon_social
                FROM solicitud_reabastecimiento_entrega sre
                LEFT JOIN agencia_transporte age_t ON age_t.id = sre.id_agencia_transporte
                WHERE sre.id_solicitud_reabastecimiento = scr.id
                ORDER BY sre.id DESC
                LIMIT 1
            ) AS agencia_transporte,
            (
                SELECT CONCAT_WS(
                    \' \',
                    NULLIF(sre.serie_guia_transportista, \'\'),
                    NULLIF(sre.numero_guia_transportista, \'\')
                )
                FROM solicitud_reabastecimiento_entrega sre
                WHERE sre.id_solicitud_reabastecimiento = scr.id
                ORDER BY sre.id DESC
                LIMIT 1
            ) AS guia_transportista
        FROM
            solicitud_reabastecimiento scr
        INNER JOIN empleado emp ON emp.id = scr.id_empleado_solicitante
        INNER JOIN almacen alm on alm.id = scr.id_almacen_solicitante
        LEFT JOIN requerimiento_almacen ra on ra.id = scr.id_requerimiento_almacen
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_solicitud !== null) {
            $sql .= ' AND scr.id = :id_solicitud';
            $params['id_solicitud'] = $id_solicitud;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen !== null) {
            $sql .= ' AND scr.id_almacen_solicitante = :id_almacen_solicitante';
            $params['id_almacen_solicitante'] = $id_almacen;
        }

        if ($mes !== null) {
            $sql .= ' AND MONTH(COALESCE(scr.fecha_solicitud, scr.created_at)) = :mes';
            $params['mes'] = $mes;
        }

        if ($yearcito !== null) {
            $sql .= ' AND YEAR(COALESCE(scr.fecha_solicitud, scr.created_at)) = :yearcito';
            $params['yearcito'] = $yearcito;
        }

        if ($id_empleado_solicitante !== null) {
            $sql .= ' AND scr.id_empleado_solicitante = :id_empleado_solicitante';
            $params['id_empleado_solicitante'] = $id_empleado_solicitante;
        }

        if ($id_requerimiento_almacen !== null) {
            $sql .= ' AND scr.id_requerimiento_almacen = :id_requerimiento_almacen';
            $params['id_requerimiento_almacen'] = $id_requerimiento_almacen;
        }

        $sql .= '
        ORDER BY
        	CASE scr.estado
                WHEN "Generada"  THEN 1
                WHEN "En Proceso" THEN 2
                WHEN "Cerrada" THEN 3
                WHEN "Anulada" THEN 4
            	ELSE 5
            END ASC,
        	COALESCE(scr.fecha_solicitud, scr.created_at) DESC
        ';

        return DB::select($sql, $params);
    }
}
