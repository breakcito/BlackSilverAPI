<?php

namespace App\Modules\Empresas\Data;

use App\Models\Empresa;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class EmpresasData
{
    /**
     * Obtener listado simple de empresas
     */
    public static function get_empresas(
        ?int $id_empresa = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $sql = '
        SELECT
            emp.id AS id_empresa,
            emp.ruc,
            emp.razon_social,
            emp.domicilio_fiscal,
            emp.url_logo,
            emp.documentos
        FROM
            empresa emp
        WHERE 1=1
        ';

        $params = [];

        if ($id_empresa !== null) {
            $sql .= ' AND emp.id = :id_empresa';
            $params['id_empresa'] = $id_empresa;
            return DB::selectOne($sql, $params);
        }

        if ($estado !== null) {
            $sql .= ' AND emp.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY razon_social ASC';

        return DB::select($sql, $params);
    }

    /**
     * Crear una nueva empresa
     */
    public static function crear_empresa(
        string $ruc,
        string $razon_social,
        ?string $domicilio_fiscal = null,
        ?string $url_logo = null,
        ?string $documentos = null
    ) {
        return Empresa::insertGetId([
            'ruc' => $ruc,
            'razon_social' => $razon_social,
            'domicilio_fiscal' => $domicilio_fiscal,
            'url_logo' => $url_logo,
            'documentos' => $documentos,
            'estado' => EstadoBase::Activo->value,
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

    /**
     * Actualizar el JSON de documentos de una empresa
     */
    public static function actualizar_documentos(int $id_empresa, ?string $documentos): bool
    {
        return (bool) Empresa::where('id', $id_empresa)->update(['documentos' => $documentos]);
    }
}
