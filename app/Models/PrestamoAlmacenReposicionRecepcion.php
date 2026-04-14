<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicionDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenReposicionRecepcion extends Model
{
    protected $table = 'prestamo_almacen_reposicion_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion',
        'id_empleado_registro', // quien recibe
        'observacion',
        'fecha_hora_recepcion',
        'evidencias', // [{"url": "", "path_relativo": "", "nombre_original": "", "extension": ""}, ...]
        'con_incidencia', // 1 | 0
        'created_at', // automatico
        'estado', // Recepcionado Parcialmente | Recepcionado
    ];

    /**
     * Crea una cabecera de recepción de reposición.
     */
    public static function crear_recepcion(
        int $id_reposicion,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias = null,
        bool $con_incidencia = false,
        EstadoPrestamoReposicionDetalle $estado = EstadoPrestamoReposicionDetalle::RecepcionCompleta
    ): int {
        return self::insertGetId([
            'id_prestamo_almacen_reposicion' => $id_reposicion,
            'id_empleado_registro' => $id_empleado,
            'fecha_hora_recepcion' => $fecha_hora_recepcion,
            'observacion' => $observacion ?? '',
            'evidencias' => $evidencias,
            'con_incidencia' => $con_incidencia ? 1 : 0,
            'estado' => $estado,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtener el historial de recepciones filtrando dinámicamente por 
     * reposiciones y/o recepciones (soporta arrays o enteros).
     */
    public static function get_recepciones(
        array|int|null $ids_reposiciones = null,
        array|int|null $ids_recepciones = null
    ) {
        $sql = "
        SELECT
            pr.id as id_recepcion,
            pr.id_prestamo_almacen_reposicion as id_reposicion,
            -- 
            CONCAT(e.nombre, ' ', e.apellido) as empleado_registro,
            --
            pr.fecha_hora_recepcion,
            pr.observacion,
            pr.evidencias,
            pr.con_incidencia,
            pr.created_at,
            pr.estado
        FROM
            prestamo_almacen_reposicion_recepcion pr
        INNER JOIN empleado e ON e.id = pr.id_empleado_registro
        WHERE 1=1
        ";

        $params = [];

        if ($ids_reposiciones !== null) {
            // Castear a array para soportar tanto un ID único como múltiples
            $ids = (array) $ids_reposiciones;
            $placeholders = [];

            foreach ($ids as $index => $id) {
                $paramName = "repo_{$index}";
                $placeholders[] = ":{$paramName}";
                $params[$paramName] = $id;
            }

            $sql .= " AND pr.id_prestamo_almacen_reposicion IN (" . implode(',', $placeholders) . ")";
        }

        if ($ids_recepciones !== null) {
            $ids = (array) $ids_recepciones;
            $placeholders = [];

            foreach ($ids as $index => $id) {
                $paramName = "recep_{$index}";
                $placeholders[] = ":{$paramName}";
                $params[$paramName] = $id;
            }

            $sql .= " AND pr.id IN (" . implode(',', $placeholders) . ")";
        }

        $sql .= " ORDER BY pr.fecha_hora_recepcion DESC";

        return DB::select($sql, $params);
    }
}
