<?php

namespace App\Data;

use App\Models\Almacen;

class AlmacenesData
{
    /**
     * Obtiene la lista de almacenes segun los parametros
     */
    public static function get_almacenes(?int $id_responsable = null, ?bool $es_principal = null): array
    {
        return Almacen::get_almacenes(id_responsable: $id_responsable, es_principal: $es_principal);
    }
}
