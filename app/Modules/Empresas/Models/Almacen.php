<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Almacen extends Model
{
    protected $table = 'almacen';

    /**
     * Listar todos los almacenes.
     * Ahora son independientes de empresa.
     */
    public static function get_almacenes()
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.estado,
            (
                SELECT CONCAT(emp.nombre, \' \', emp.apellido)
                FROM responsable_almacen ra
                INNER JOIN usuario u ON u.id = ra.id_usuario
                INNER JOIN empleado emp ON emp.id = u.id_empleado
                WHERE ra.id_almacen = a.id AND ra.estado = :estado_activo
                LIMIT 1
            ) AS responsable_actual,
            (
                SELECT COUNT(*)
                FROM almacen_labor al
                WHERE al.id_almacen = a.id
            ) AS labores_count
        FROM
            almacen a
        WHERE
            a.estado = :estado
        ORDER BY a.es_principal DESC, a.nombre ASC
        ';
        
        return DB::select($sql, [
            'estado'        => EstadoBase::Activo->value,
            'estado_activo' => EstadoBase::Activo->value
        ]);
    }

    public static function verificar_nombre_existente(string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('almacen')
            ->where('nombre', $nombre)
            ->where('estado', EstadoBase::Activo->value);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    public static function crear_almacen(string $nombre, ?string $descripcion, bool $es_principal)
    {
        return DB::table('almacen')->insertGetId([
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'es_principal'       => $es_principal,
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    public static function get_almacen_by_id(int $id)
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.estado
        FROM
            almacen a
        WHERE
            a.id = :id AND
            a.estado = :estado
        ';

        return DB::selectOne($sql, [
            'id'     => $id,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    // --- ALMACÉN - LABOR (NUEVA RELACIÓN PRINCIPAL) ---

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
    
    // Obtener las labores a las que atiende este almacén
    public static function get_labores_asignadas(int $id_almacen)
    {
         $sql = '
        SELECT
            al.id,
            l.nombre AS labor,
            tl.nombre AS tipo_labor,
            m.nombre AS mina
        FROM
            almacen_labor al
        INNER JOIN labor l ON l.id = al.id_labor
        INNER JOIN mina m ON m.id = l.id_mina
        INNER JOIN tipo_labor tl ON tl.id = l.id_tipo_labor
        WHERE
            al.id_almacen = :id_almacen
        ORDER BY m.nombre ASC, l.nombre ASC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }

    // --- ALMACÉN - RESPONSABLE (responsable_almacen) ---

    public static function asignar_responsable(int $id_almacen, int $id_usuario, string $fecha_inicio, ?string $fecha_fin)
    {
        return DB::table('responsable_almacen')->insertGetId([
            'id_almacen'         => $id_almacen,
            'id_usuario'         => $id_usuario,
            'fecha_inicio'       => $fecha_inicio,
            'fecha_fin'          => $fecha_fin,
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    public static function cerrar_responsable_activo(int $id_almacen, string $fecha_cierre)
    {
        return DB::table('responsable_almacen')
            ->where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_cierre,
                'estado'    => EstadoBase::Inactivo->value
            ]);
    }

    public static function get_responsables_historial(int $id_almacen)
    {
        $sql = '
        SELECT
            ra.id AS id_asignacion,
            ra.id_usuario,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            ra.fecha_inicio,
            ra.fecha_fin,
            ra.estado
        FROM
            responsable_almacen ra
        INNER JOIN usuario u ON u.id = ra.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            ra.id_almacen = :id_almacen
        ORDER BY ra.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
