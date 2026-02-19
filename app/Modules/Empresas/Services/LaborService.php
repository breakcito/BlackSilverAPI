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

    public function crear_labor(int $id_empresa, int $id_mina, int $id_tipo_labor, string $nombre, ?string $descripcion, string $tipo_sostenimiento)
    {
        // 1. Verificar nombre duplicado en la misma mina
        if (Labor::verificar_labor_existente($id_mina, $nombre)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta mina');
        }

        // 2. Obtener prefijo de tipo de labor
        $tipoLabor = TipoLabor::get_tipo_labor_by_id($id_tipo_labor);
        if (!$tipoLabor) {
            return ApiResponse::error('Tipo de labor no encontrado');
        }
        
        $prefijo = $tipoLabor->codigo;
        $codigo_correlativo = CorrelativoHelper::generar('labor', 'codigo_correlativo', $prefijo, 4, true);

        $id_labor = Labor::crear_labor(
            $id_empresa,
            $id_mina,
            $id_tipo_labor,
            $codigo_correlativo,
            $nombre,
            $descripcion,
            $tipo_sostenimiento
        );
        return ApiResponse::success(Labor::get_labor_by_id($id_labor));
    }

    public function update_labor(int $id, int $id_empresa, int $id_mina, int $id_tipo_labor, string $nombre, ?string $descripcion, string $tipo_sostenimiento)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        // 2. Verificar nombre duplicado (excluyendo la actual)
        if (Labor::verificar_labor_existente($id_mina, $nombre, $id)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta mina');
        }

        Labor::update_labor(
            $id,
            $id_empresa,
            $id_mina,
            $id_tipo_labor,
            $labor->codigo_correlativo, // Se mantiene el que tenía originamente
            $nombre,
            $descripcion,
            $tipo_sostenimiento
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
        return ApiResponse::success(['mensaje' => 'Labor eliminada correctamente']);
    }

    public function asignar_responsable_labor(int $id_labor, int $id_usuario, string $fecha_inicio)
    {
        // 1. Verificar si la labor existe
        $labor = Labor::get_labor_by_id($id_labor);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }
        
        // 1.1 Obtener la mina para saber la concesion
        $mina = \App\Modules\Empresas\Models\Mina::get_mina_by_id($labor->id_mina);
        if (!$mina) {
             return ApiResponse::error('Mina asociada no encontrada');
        }

        // 2. VALIDAR USUARIO AUTORIZADO (Contrato vigente en concesión)
        if (!Labor::check_usuario_autorizado($id_usuario, $mina->id_concesion)) {
            return ApiResponse::error('El usuario no pertenece a una empresa con contrato vigente en esta concesión.');
        }

        // 3. Cerrar responsable activo si existe
        Labor::cerrar_responsable_activo($id_labor, $fecha_inicio);

        // 4. Crear nuevo responsable
        Labor::asignar_responsable($id_labor, $id_usuario, $fecha_inicio, null);

        return ApiResponse::success(['mensaje' => 'Responsable asignado correctamente']);
    }

    public function get_responsables_labor(int $id_labor)
    {
        $responsables = Labor::get_responsables_historial($id_labor);
        return ApiResponse::success($responsables);
    }
}
