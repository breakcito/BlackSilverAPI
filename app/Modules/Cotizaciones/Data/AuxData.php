<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\Empresa;
use App\Shared\Enums\_Generic\EstadoBase;

class AuxData
{
    public static function get_empresas()
    {
        return Empresa::select(
            "id as id_empresa",
            "razon_social"
        )->get();
    }

}
