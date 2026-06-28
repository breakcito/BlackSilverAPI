<?php

namespace App\Modules\Organigrama\Data;

use App\Models\Cargo;
use App\Shared\Enums\_Generic\EstadoBase;

class CargosData
{
    /**
     * Alternar estado
     */
    public static function cambiar_estado(int $id_cargo): string
    {
        $cargo = Cargo::findOrFail($id_cargo);
        $nuevo_estado = $cargo->estado === EstadoBase::Activo->value
            ? EstadoBase::Inactivo->value
            : EstadoBase::Activo->value;

        $cargo->update(['estado' => $nuevo_estado]);

        return $nuevo_estado;
    }
}
