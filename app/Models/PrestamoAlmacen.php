<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamo;
use App\Shared\Enums\PrestamoAlmacen\EstadoReposicion;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacen extends Model
{
    protected $table = 'prestamo_almacen';

    public $timestamps = false;

    protected $fillable = [
        // opcional, el prestamo puede nacer de una solicitud de reabastecimiento
        'id_solicitud_reabastecimiento',
        'id_almacen_solicitante', // el que necesita stock
        'id_almacen_prestamista', // el que va a prestar stock
        'id_empleado_registro', // quien lo registro
        'correlativo', // prefijo: PRT
        'numero_correlativo',
        'fecha_hora_prestamo', // cuando se realizo ese prestamo
        'fecha_limite_devolucion',
        'observacion',
        'created_at',
        // Sin reposicion / Reposicion parcial / Reposicion total
        'estado_reposicion',
        // Generado / En proceso (a penas se realiza una entrega) / Completado / Cerrado / Anulado
        'estado',
    ];

    /**
     * Obtener un nuevo correlativo para un prestamo
     */
    public static function get_nuevo_correlativo(int $id_almacen_prestamista)
    {
        return CorrelativoHelper::generar(
            'prestamo_almacen',
            'PRT',
            ['id_almacen_prestamista' => $id_almacen_prestamista]
        );
    }

    /**
     * Obtiene un prestamo o el historial completo en base al almacen prestamista, mes y año
     */
    public static function get_prestamos(
        ?int $id_prestamo = null,
        ?int $id_almacen_prestamista = null,
        ?int $mes = null,
        ?int $yearcito = null,
        ?int $id_solicitud_rebastecimiento = null,
    ): array {
        $sql = '
        SELECT
            pa.id AS id_prestamo,
            pa.correlativo,
            --
            pa.id_almacen_solicitante,
            alm_sol.nombre as almacen_solicitante,
            --
            pa.id_almacen_prestamista,
            alm_pr.nombre as almacen_prestamista,
            --
            pa.id_solicitud_reabastecimiento,
            sr.correlativo as solicitud_reabastecimiento,
            --
            pa.fecha_hora_prestamo,
            pa.fecha_limite_devolucion,
            pa.observacion,
            CONCAT(e.nombre, " ", e.apellido) AS registrado_por,    
            --
            pa.created_at,
            pa.estado_reposicion,
            pa.estado
        FROM
            prestamo_almacen pa
        LEFT JOIN solicitud_reabastecimiento sr ON
            sr.id = pa.id_solicitud_reabastecimiento
        INNER JOIN almacen alm_sol ON
            alm_sol.id = pa.id_almacen_solicitante
        INNER JOIN almacen alm_pr ON
            alm_pr.id = pa.id_almacen_prestamista
        INNER JOIN empleado e ON
            e.id = pa.id_empleado_registro
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_prestamo) {
            $sql .= "AND pa.id = :id_prestamo";
            $params['id_prestamo'] = $id_prestamo;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen_prestamista) {
            $sql .= "AND pa.id_almacen_prestamista = :id_almacen_prestamista";
            $params['id_almacen_prestamista'] = $id_almacen_prestamista;
        }

        if ($mes) {
            $sql .= "AND MONTH(pa.created_at) = :mes";
            $params['mes'] = $mes;
        }

        if ($yearcito) {
            $sql .= "AND YEAR(pa.created_at) = :yearcito";
            $params['yearcito'] = $yearcito;
        }

        if ($id_solicitud_rebastecimiento) {
            $sql .= "AND pa.id_solicitud_reabastecimiento = :id_solicitud_rebastecimiento";
            $params['id_solicitud_reabastecimiento'] = $id_solicitud_rebastecimiento;
        }

        $sql .= "ORDER BY pa.fecha_hora_prestamo DESC;";

        return DB::select($sql, $params);
    }

    public static function crear_prestamo(
        int $id_solicitud_reabastecimiento,
        int $id_almacen_solicitante,
        int $id_almacen_prestamista,
        int $id_empleado_registro,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_prestamo,
        ?string $fecha_limite_devolucion,
        ?string $observacion,
    ): int {
        return self::insertGetId([
            'id_solicitud_reabastecimiento' => $id_solicitud_reabastecimiento,
            'id_almacen_solicitante'        => $id_almacen_solicitante,
            'id_almacen_prestamista'        => $id_almacen_prestamista,
            'id_empleado_registro'          => $id_empleado_registro,
            'correlativo'                   => $correlativo,
            'numero_correlativo'            => $numero_correlativo,
            'fecha_hora_prestamo'           => $fecha_hora_prestamo,
            'fecha_limite_devolucion'       => $fecha_limite_devolucion,
            'observacion'                   => $observacion,
            'created_at'                    => now(),
            'estado_reposicion'             => EstadoReposicion::SinReposicion->value,
            'estado'                        => EstadoPrestamo::Generado->value,
        ]);
    }
}
