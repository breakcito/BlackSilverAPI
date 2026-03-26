<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\Almacen;

class AuxData
{
    /**
     * Obtiene los almacenes secundarios.
     */
    public static function get_almacenes_secundarios(): array
    {
        return Almacen::get_almacenes(es_principal: 0);
    }
}
