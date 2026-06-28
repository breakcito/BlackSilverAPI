<?php

namespace App\Modules\Empresas\Data;

use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

class EmpresasData
{
    /**
     * Crear una nueva empresa
     */
    public static function crear_empresa(string $ruc, string $razon_social, ?string $url_logo = null)
    {
        return Empresa::insertGetId([
            'ruc' => $ruc,
            'razon_social' => $razon_social,
            'url_logo' => $url_logo,
        ]);
    }

    /**
     * Verificar si ya existe una empresa con el mismo RUC
     */
    public static function verificar_ruc_duplicado(string $ruc)
    {
        return Empresa::where('ruc', $ruc)->exists();
    }

    /**
     * Actualizar la ruta del logo de una empresa
     */
    public static function actualizar_logo(int $id_empresa, ?string $url_logo = null): bool
    {
        return (bool) Empresa::where('id', $id_empresa)->update(['url_logo' => $url_logo]);
    }
}
