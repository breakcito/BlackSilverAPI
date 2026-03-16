<?php

namespace App\Views\Empresas;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\Empresas\Data\EmpresasData;
use Illuminate\Support\Facades\Request as FacadesRequest;

class EmpresasService
{
    /**
     * Obtener el listado de empresas
     */
    public static function get_empresas()
    {
        $empresas = EmpresasData::get_empresas();

        // Convertir path_logo a URL completa
        foreach ($empresas as $empresa) {
            if ($empresa->path_logo) {
                $empresa->path_logo = asset('storage/' . $empresa->path_logo);
            }
        }

        return ApiResponse::success($empresas);
    }

    /**
     * Crear una nueva empresa
     */
    public static function crear_empresa(array $data)
    {
        if (EmpresasData::verificar_ruc_duplicado($data['ruc'])) {
            return ApiResponse::error('Ya existe una empresa registrada con este RUC.');
        }

        // Si viene un archivo en path_logo, lo procesamos
        $request = request();
        if ($request->hasFile('path_logo')) {
            $archivos = ArchivoHelper::guardarArchivos('logos-empresas', [$request->file('path_logo')]);
            if (!empty($archivos)) {
                $data['path_logo'] = $archivos[0]['relative_path'];
            }
        }

        $id_empresa = EmpresasData::crear_empresa($data);
        $nuevaEmpresa = EmpresasData::get_empresa_by_id($id_empresa);

        if ($nuevaEmpresa->path_logo) {
            $nuevaEmpresa->path_logo = asset('storage/' . $nuevaEmpresa->path_logo);
        }

        return ApiResponse::success($nuevaEmpresa, 'Empresa registrada correctamente');
    }
}
