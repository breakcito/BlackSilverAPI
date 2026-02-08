<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla empleado.
 *
 * @property int $id
 * @property int $id_cargo_empresa
 * @property string $nombre
 * @property string $apellido
 * @property string|null $dni
 * @property string|null $ruc
 * @property string|null $carnet_extranjeria
 * @property string|null $pasaporte
 * @property string|null $fecha_nacimiento
 * @property string|null $estado
 */
class Empleado extends Model
{
    protected $table = 'empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_cargo_empresa',
        'nombre',
        'apellido',
        'dni',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
        'estado',
    ];

    /**
     * Crear un nuevo empleado.
     */
    public static function crearEmpleado(
        int $idCargoEmpresa,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnetExtranjeria = null,
        ?string $pasaporte = null,
        ?string $fechaNacimiento = null,
        string $estado = 'Activo'
    ): ?Empleado {
        return self::create([
            'id_cargo_empresa' => $idCargoEmpresa,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnetExtranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fechaNacimiento,
            'estado' => $estado,
        ]);
    }

    /**
     * Buscar empleado por ID.
     */
    public static function buscarPorId(int $id): ?Empleado
    {
        return self::find($id);
    }

    /**
     * Buscar empleado por cualquier documento.
     */
    public static function buscarPorDocumento(
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnetExtranjeria = null,
        ?string $pasaporte = null
    ): ?Empleado {
        $query = self::query();

        if ($dni) {
            $query->orWhere('dni', $dni);
        }
        if ($ruc) {
            $query->orWhere('ruc', $ruc);
        }
        if ($carnetExtranjeria) {
            $query->orWhere('carnet_extranjeria', $carnetExtranjeria);
        }
        if ($pasaporte) {
            $query->orWhere('pasaporte', $pasaporte);
        }

        return $query->first();
    }
}
