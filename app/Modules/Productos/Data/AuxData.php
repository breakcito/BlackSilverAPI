<?php

namespace App\Modules\Productos\Data;

use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * Obtener categorías de tipo "Bien" internas para esta vista
     */
    public static function get_categorias()
    {
        return DB::select('
            SELECT 
                id AS id_categoria, 
                nombre,
                es_auditable,
                clasificacion_bien
            FROM categoria
            WHERE estado = "Activo"
            ORDER BY nombre ASC
        ');
    }
}
