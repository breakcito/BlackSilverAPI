<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Almacen extends Model
{
    protected $table = 'almacen';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'descripcion',
        'es_principal',
        'estado',
    ];

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
                FROM almacen_mina am
                WHERE am.id_almacen = a.id
            ) AS minas_count
        FROM
            almacen a
        WHERE
            a.estado = :estado
        ORDER BY a.es_principal DESC, a.nombre ASC
        ';

        return DB::select($sql, [
            'estado' => EstadoBase::Activo->value,
            'estado_activo' => EstadoBase::Activo->value,
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
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_principal' => $es_principal,
            'estado' => EstadoBase::Activo->value,
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
            'id' => $id,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    // --- ALMACÉN - MINA (NUEVA RELACIÓN PRINCIPAL) ---

    public static function asignar_mina(int $id_almacen, int $id_mina)
    {
        return DB::table('almacen_mina')->insertGetId([
            'id_almacen' => $id_almacen,
            'id_mina' => $id_mina,
        ]);
    }

    public static function verificar_mina_asignada(int $id_almacen, int $id_mina)
    {
        return DB::table('almacen_mina')
            ->where('id_almacen', $id_almacen)
            ->where('id_mina', $id_mina)
            ->exists();
    }

    // Obtener las minas a las que atiende este almacén
    public static function get_minas_asignadas(int $id_almacen)
    {
        $sql = '
        SELECT
            am.id,
            m.nombre AS mina,
            c.nombre AS concesion
        FROM
            almacen_mina am
        INNER JOIN mina m ON m.id = am.id_mina
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            am.id_almacen = :id_almacen
        ORDER BY m.nombre ASC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }

    public static function desasignar_mina(int $id_asignacion)
    {
        return DB::table('almacen_mina')->where('id', $id_asignacion)->delete();
    }

    // --- ALMACÉN - RESPONSABLE (responsable_almacen) ---

    public static function asignar_responsable(int $id_almacen, int $id_usuario, string $fecha_inicio, ?string $fecha_fin)
    {
        return DB::table('responsable_almacen')->insertGetId([
            'id_almacen' => $id_almacen,
            'id_usuario' => $id_usuario,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    public static function cerrar_responsable_activo(int $id_almacen, string $fecha_cierre)
    {
        return DB::table('responsable_almacen')
            ->where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_cierre,
                'estado' => EstadoBase::Inactivo->value,
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
