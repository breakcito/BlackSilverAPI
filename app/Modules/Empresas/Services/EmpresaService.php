<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Empresa;
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
        // 1. Verificar si el RUC ya existe
        if (Empresa::verificar_empresa_existente($ruc)) {
            return ApiResponse::error('Ya existe una empresa con este RUC.');
        }

        // 2. Crear
        $id = Empresa::crear_empresa($ruc, $razon_social, $nombre_comercial, $abreviatura, $path_logo);
        
        return ApiResponse::success(Empresa::get_empresa_by_id($id), 'Empresa creada correctamente');
    }

    public function get_usuarios_por_empresa(int $id_empresa)
    {
        // Validar si existe la empresa sería ideal, pero por eficiencia vamos directo.
        $usuarios = Empresa::get_usuarios_por_empresa($id_empresa);
        return ApiResponse::success($usuarios);
    }
}
