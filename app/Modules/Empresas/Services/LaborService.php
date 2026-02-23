<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Labor;
use App\Modules\Empresas\Models\TipoLabor;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;

class LaborService
{
    public function get_labores(?int $id_mina = null)
    {
        $labores = Labor::get_labores($id_mina);
        return ApiResponse::success($labores);
    }

    public function get_labor_by_id(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }
        return ApiResponse::success($labor);
    }

    public function crear_labor(
        int $id_empresa, 
        int $id_mina, 
        int $id_tipo_labor, 
        string $nombre, 
        ?string $descripcion, 
        string $tipo_sostenimiento,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null
    ) {
        if (Labor::verificar_labor_existente($id_mina, $nombre)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta mina');
        }

        $mina = \App\Modules\Empresas\Models\Mina::get_mina_by_id($id_mina);
        if (!$mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        if (!\App\Modules\Empresas\Models\Mina::check_contrato_vigente($mina->id_concesion, $id_empresa)) {
            return ApiResponse::error('La empresa seleccionada no tiene un contrato vigente en la concesión de esta mina.');
        }

        // 3. Obtener prefijo de tipo de labor
        $tipoLabor = TipoLabor::get_tipo_labor_by_id($id_tipo_labor);
        if (!$tipoLabor) {
            return ApiResponse::error('Tipo de labor no encontrado');
        }
        
        $prefijo = $tipoLabor->codigo;
        $codigo_correlativo = CorrelativoHelper::generar('labor', 'codigo_correlativo', $prefijo, 4, ['id_empresa' => $id_empresa]);

        $id_labor = Labor::crear_labor(
            $id_empresa,
            $id_mina,
            $id_tipo_labor,
            $codigo_correlativo,
            $nombre,
            $descripcion,
            $tipo_sostenimiento,
            $veta,
            $ancho,
            $alto,
            $nivel
        );
        return ApiResponse::success(Labor::get_labor_by_id($id_labor));
    }

    public function update_labor(
        int $id, 
        int $id_empresa, 
        int $id_mina, 
        int $id_tipo_labor, 
        string $nombre, 
        ?string $descripcion, 
        string $tipo_sostenimiento,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null,
        ?string $fecha_fin = null,
        ?string $estado = null
    ) {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        if (Labor::verificar_labor_existente($id_mina, $nombre, $id)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta mina');
        }

        $mina = \App\Modules\Empresas\Models\Mina::get_mina_by_id($id_mina);
        if (!$mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        if (!\App\Modules\Empresas\Models\Mina::check_contrato_vigente($mina->id_concesion, $id_empresa)) {
            return ApiResponse::error('La empresa seleccionada no tiene un contrato vigente en la concesión de esta mina.');
        }

        Labor::update_labor(
            $id,
            $id_empresa,
            $id_mina,
            $id_tipo_labor,
            $labor->codigo_correlativo, // Se mantiene el que tenía originamente
            $nombre,
            $descripcion,
            $tipo_sostenimiento,
            $veta,
            $ancho,
            $alto,
            $nivel,
            $fecha_fin,
            $estado
        );

        return ApiResponse::success(['mensaje' => 'Labor actualizada correctamente']);
    }

    public function delete_labor(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        Labor::delete_labor($id);
        return ApiResponse::success(['mensaje' => 'Labor inactivada correctamente (finalizada)']);
    }
}
