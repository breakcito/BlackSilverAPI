<?php

namespace App\Modules\Empresas\Service;

use App\Data\OficinasData;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\Empresas\Data\EmpresasData;
use App\Data\EmpresasData as EmpresasDataGlobal;
use Illuminate\Http\UploadedFile;

class EmpresasService
{
    /**
     * Obtener el listado de empresas
     */
    public static function get_empresas()
    {
        $empresas = EmpresasDataGlobal::get_empresas();

        // recopilar id's unicos de todas las empresas
        $ids_empresas = array_unique(array_column($empresas, 'id_empresa'));

        // obtener las oficinas de todas las empresas
        $oficinas = OficinasData::get_oficinas(id_empresa: $ids_empresas);

        // asociar oficinas a cada empresa
        foreach ($empresas as $empresa) {
            $empresa['oficinas'] = $oficinas->where('id_empresa', $empresa['id_empresa']);
        }

        return ApiResponse::success($empresas);
    }

    /**
     * Crear una nueva empresa
     */
    public static function crear_empresa(string $ruc, string $razon_social, ?UploadedFile $logo = null)
    {
        if (EmpresasData::verificar_ruc_duplicado($ruc)) {
            return ApiResponse::error('Ya existe una empresa registrada con este RUC.');
        }

        $url_logo_str = null;
        if ($logo && $logo->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('logos-empresas', [$logo])[0] ?? null;
            if ($archivo && isset($archivo['url'])) {
                $url_logo_str = $archivo['url'];
            }
        }

        $id_empresa = EmpresasData::crear_empresa($ruc, $razon_social, $url_logo_str);
        $nuevaEmpresa = EmpresasDataGlobal::get_empresas(id_empresa: $id_empresa);

        return ApiResponse::success($nuevaEmpresa, 'Empresa registrada correctamente');
    }

    /**
     * Actualizar el logo de una empresa
     */
    public static function actualizar_logo(int $id_empresa, ?UploadedFile $nuevo_logo = null)
    {
        $empresa = EmpresasDataGlobal::get_empresa_dinamica_by_id(id_empresa: $id_empresa, columnas: ['url_logo']);
        $url_logo_old = !empty($empresa['url_logo']) ? $empresa['url_logo'] : null;

        // Caso: eliminar logo (sin nuevo)
        if (is_null($nuevo_logo)) {
            if ($url_logo_old) {
                ArchivoHelper::eliminarArchivo($url_logo_old);
                EmpresasData::actualizar_logo($id_empresa, null);

                return ApiResponse::success(null, 'Logo eliminado correctamente.');
            }

            return ApiResponse::success(null, 'No hay logo para eliminar.');
        }

        if (!$nuevo_logo->isValid()) {
            return ApiResponse::error('Archivo no válido.');
        }

        // Caso: actualizar o agregar logo
        if ($url_logo_old) {
            ArchivoHelper::eliminarArchivo($url_logo_old);
        }

        $resultado = ArchivoHelper::guardarArchivos('logos-empresas', [$nuevo_logo]);
        $url_logo = $resultado[0]['url'] ?? null;

        if (empty($url_logo)) {
            return ApiResponse::error('Error al procesar el archivo.');
        }

        EmpresasData::actualizar_logo(
            id_empresa: $id_empresa,
            url_logo: $url_logo
        );

        return ApiResponse::success($url_logo, 'Logo actualizado correctamente.');
    }
}
