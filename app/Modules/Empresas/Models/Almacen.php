<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Almacen extends Model
{
    /**
     * Listar almacenes de una empresa.
     * Incluye información del responsable actual y conteo de labores asignadas.
     */
    public static function get_almacenes(?int $id_empresa = null)
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.id_empresa,
            e.nombre_comercial AS empresa,
            a.correlativo,
            a.numero_correlativo,
            CONCAT(a.correlativo, \'-\', LPAD(a.numero_correlativo, 3, \'0\')) AS codigo,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.estado,
            (SELECT COUNT(*) FROM almacen_labor al WHERE al.id_almacen = a.id) AS labores_count,
            (
                SELECT CONCAT(emp.nombre, \' \', emp.apellido)
                FROM almacen_usuario au
                INNER JOIN usuario_empresa ue ON ue.id = au.id_usuario_empresa
                INNER JOIN usuario u ON u.id = ue.id_usuario
                INNER JOIN empleado emp ON emp.id = u.id_empleado
                WHERE au.id_almacen = a.id AND au.estado = :estado_activo
                LIMIT 1
            ) AS responsable_actual
        FROM
            almacen a
        INNER JOIN empresa e ON e.id = a.id_empresa
        WHERE
            a.estado = :estado
        ';

        $params = [
            'estado'        => EstadoBase::Activo->value,
            'estado_activo' => EstadoBase::Activo->value
        ];

        if ($id_empresa) {
            $sql .= ' AND a.id_empresa = :id_empresa';
            $params['id_empresa'] = $id_empresa;
        }

        $sql .= ' ORDER BY a.es_principal DESC, a.numero_correlativo ASC';

        return DB::select($sql, $params);
    }

    /**
     * Verificar nombre único por empresa.
     */
    public static function verificar_nombre_existente(int $id_empresa, string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('almacen')
            ->where('id_empresa', $id_empresa)
            ->where('nombre', $nombre)
            ->where('estado', EstadoBase::Activo->value);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    /**
     * Obtener el último correlativo numérico para una empresa.
     */
    public static function get_ultimo_correlativo(int $id_empresa)
    {
        return DB::table('almacen')
            ->where('id_empresa', $id_empresa)
            ->max('numero_correlativo') ?? 0;
    }

    /**
     * Crear un nuevo almacén.
     */
    public static function crear_almacen(int $id_empresa, string $correlativo, int $numero_correlativo, string $nombre, ?string $descripcion, bool $es_principal)
    {
        return DB::table('almacen')->insertGetId([
            'id_empresa'         => $id_empresa,
            'correlativo'        => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'es_principal'       => $es_principal,
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    /**
     * Obtener almacén por ID.
     */
    public static function get_almacen_by_id(int $id)
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.id_empresa,
            e.nombre_comercial AS empresa,
            a.correlativo,
            a.numero_correlativo,
            CONCAT(a.correlativo, \'-\', LPAD(a.numero_correlativo, 3, \'0\')) AS codigo,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.estado
        FROM
            almacen a
        INNER JOIN empresa e ON e.id = a.id_empresa
        WHERE
            a.id = :id AND
            a.estado = :estado
        ';

        return DB::selectOne($sql, [
            'id'     => $id,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    // --- ALMACÉN - USUARIO (RESPONSABLES) ---

    /**
     * Asignar responsable (cierra el anterior activo automáticamente si existe).
     * Nota: La lógica de cierre se puede manejar aquí o en el servicio. Aquí insertamos el nuevo.
     */
    public static function asignar_responsable(int $id_almacen, int $id_usuario_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        return DB::table('almacen_usuario')->insertGetId([
            'id_almacen'         => $id_almacen,
            'id_usuario_empresa' => $id_usuario_empresa,
            'fecha_inicio'       => $fecha_inicio,
            'fecha_fin'          => $fecha_fin,
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    /**
     * Cerrar responsable activo actual.
     */
    public static function cerrar_responsable_activo(int $id_almacen, string $fecha_cierre)
    {
        return DB::table('almacen_usuario')
            ->where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_cierre,
                'estado'    => EstadoBase::Inactivo->value
            ]);
    }

    /**
     * Obtener historial de responsables.
     */
    public static function get_responsables_historial(int $id_almacen)
    {
        $sql = '
        SELECT
            au.id AS id_asignacion,
            au.id_usuario_empresa,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            au.fecha_inicio,
            au.fecha_fin,
            au.estado
        FROM
            almacen_usuario au
        INNER JOIN usuario_empresa ue ON ue.id = au.id_usuario_empresa
        INNER JOIN usuario u ON u.id = ue.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            au.id_almacen = :id_almacen
        ORDER BY au.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }

    // --- ALMACÉN - LABOR (ASIGNACIONES) ---

    public static function asignar_labor(int $id_almacen, int $id_labor)
    {
        return DB::table('almacen_labor')->insertGetId([
            'id_almacen' => $id_almacen,
            'id_labor'   => $id_labor
        ]);
    }

    public static function verificar_labor_asignada(int $id_almacen, int $id_labor)
    {
        return DB::table('almacen_labor')
            ->where('id_almacen', $id_almacen)
            ->where('id_labor', $id_labor)
            ->exists();
    }
    
    public static function get_labores_asignadas(int $id_almacen)
    {
         $sql = '
        SELECT
            al.id,
            l.nombre AS labor,
            l.tipo_labor,
            cn.nombre AS concesion
        FROM
            almacen_labor al
        INNER JOIN labor l ON l.id = al.id_labor
        INNER JOIN empresa_concesion ec ON ec.id = l.id_empresa_concesion
        INNER JOIN concesion cn ON cn.id = ec.id_concesion
        WHERE
            al.id_almacen = :id_almacen
        ORDER BY l.nombre ASC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
