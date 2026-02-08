<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Modelo para la tabla modulo.
 *
 * Representa los módulos principales del sistema para
 * organizar la navegación y permisos.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property EstadoBase $estado
 */
class Modulo extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'modulo';

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    /**
     * Casting de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoBase::class,
        ];
    }

    /**
     * Relación con submódulos.
     */
    public function submodulos(): HasMany
    {
        return $this->hasMany(Submodulo::class, 'id_modulo');
    }

    /**
     * Ejemplo de consulta SQL compleja usando DB facade.
     *
     * Obtiene módulos con conteo de submódulos activos.
     *
     * @return array<int, object>
     */
    public static function obtenerModulosConConteoSubmodulos(): array
    {
        $sql = '
            SELECT
                m.id,
                m.nombre,
                m.descripcion,
                m.estado,
                COUNT(s.id) as total_submodulos
            FROM modulo m
            LEFT JOIN submodulo s ON s.id_modulo = m.id AND s.estado = ?
            WHERE m.estado = ?
            GROUP BY m.id, m.nombre, m.descripcion, m.estado
            ORDER BY m.nombre
        ';

        return DB::select($sql, [
            EstadoBase::Activo->value,
            EstadoBase::Activo->value,
        ]);
    }
}
