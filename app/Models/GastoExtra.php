<?php

namespace App\Models;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Database\Eloquent\Model;

// esta tabla registrara todos los gastos extra de la empresa
// como servicios, averias, donaciones, etc
class GastoExtra extends Model
{
    protected $table = 'gasto_extra';

    public $timestamps = false;

    protected $fillable = [
        'id_labor',
        'id_empleado_registro',
        'descripcion',
        'monto',
        'created_at',
        'estado',
    ];

    /**
     * Registrar un nuevo gasto extra.
     */
    public static function crear_gasto(
        int $id_empleado_registro,
        int $id_labor,
        string $descripcion,
        float $monto,
        EstadoBase $estado = EstadoBase::Activo
    ): int {
        return self::insertGetId([
            'id_empleado_registro' => $id_empleado_registro,
            'id_labor' => $id_labor,
            'descripcion' => $descripcion,
            'monto' => $monto,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado->value,
        ]);
    }
}
