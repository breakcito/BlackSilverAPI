<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Empleado extends Model
{
    protected $table = 'empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_cargo',
        'id_empresa',
        'nombre',
        'apellido',
        'dni',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
        'path_foto',
        'estado',
    ];

    /**
     * Obtener listado de empleados con información de cargo y empresa.
     */
    public static function get_empleados()
    {
        $sql = '
        SELECT
            e.id as id_empleado,
            e.id_cargo,
            c.nombre as cargo,
            e.id_empresa,
            em.nombre_comercial as empresa,
            e.nombre,
            e.apellido,
            e.dni,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.path_foto,
            e.estado
        FROM
            empleado e
        INNER JOIN cargo c ON c.id = e.id_cargo
        INNER JOIN empresa em ON em.id = e.id_empresa
        ORDER BY e.apellido ASC
        ';

        return DB::select($sql);
    }

    /**
     * Obtener empleado por ID (para retorno post-creación).
     */
    public static function get_empleado_by_id(int $id)
    {
        $sql = '
        SELECT
            e.id as id_empleado,
            e.id_cargo,
            c.nombre as cargo,
            e.id_empresa,
            em.nombre_comercial as empresa,
            e.nombre,
            e.apellido,
            e.dni,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.path_foto,
            e.estado
        FROM
            empleado e
        INNER JOIN cargo c ON c.id = e.id_cargo
        INNER JOIN empresa em ON em.id = e.id_empresa
        WHERE
            e.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    /**
     * Verificar DNI existente.
     */
    public static function verificar_documento_existente(string $columna, string $valor, ?int $id_excluir = null)
    {
        $query = self::where($columna, $valor);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    /**
     * Crear un nuevo empleado.
     */
    public static function crear_empleado(
        int $id_cargo,
        int $id_empresa,
        string $nombre,
        string $apellido,
        ?string $dni,
        ?string $ruc,
        ?string $carnet_extranjeria,
        ?string $pasaporte,
        ?string $fecha_nacimiento,
        ?string $path_foto
    ) {
        return self::insertGetId([
            'id_cargo' => $id_cargo,
            'id_empresa' => $id_empresa,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fecha_nacimiento,
            'path_foto' => $path_foto,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
