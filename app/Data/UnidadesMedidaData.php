<?php

namespace App\Data;

use App\Models\UnidadMedida;

class UnidadesMedidaData
{
    /**
     * Obtener las unidades de medida
     */
    public static function get_unidades(?int $id_unidad_medida = null, ?bool $solo_base = null)
    {
        return UnidadMedida::get_unidades($id_unidad_medida, $solo_base ? 1 : 0);
    }
}
