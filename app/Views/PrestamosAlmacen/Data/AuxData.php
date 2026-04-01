<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\Almacen;

class AuxData
{
    /**
     * Obtiene los almacenes.
     */
    public static function get_almacenes(bool $es_principal = false): array
    {
        return Almacen::get_almacenes(es_principal: $es_principal ? 1 : 0);
    }
}
