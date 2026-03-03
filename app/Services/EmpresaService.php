<?php

namespace App\Services;

use App\Models\Empresa;
use App\Shared\Responses\ApiResponse;

class EmpresaService
{
    public function get_empresas()
    {
        $empresas = Empresa::get_empresas();

        return ApiResponse::success($empresas);
    }

    public function get_empresas_by_usuario(int $id_usuario)
    {
        $empresas = Empresa::get_empresas_by_usuario($id_usuario);

        return ApiResponse::success($empresas);
    }

    /**
     * Crear una nueva empresa
     */
    public function crear_empresa(string $ruc, string $razon_social, string $nombre_comercial, string $abreviatura, string $path_logo)
    {
        // Verificar si el RUC ya existe
        if (Empresa::where('ruc', $ruc)->exists()) {
            return ApiResponse::error('Ya existe una empresa con este RUC.');
        }

        // Crear
        $empresa = Empresa::create([
            'ruc' => $ruc,
            'razon_social' => $razon_social,
            'nombre_comercial' => $nombre_comercial,
            'abreviatura' => $abreviatura,
            'path_logo' => $path_logo,
        ]);

        $nuevaEmpresa = Empresa::get_empresas($empresa->id)[0];

        return ApiResponse::success($nuevaEmpresa, 'Empresa creada correctamente');
    }
}
