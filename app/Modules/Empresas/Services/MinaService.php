<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Mina;
use App\Shared\Responses\ApiResponse;

class MinaService
{
    /**
     * Listar minas.
     */
    public function get_minas(?int $id_concesion)
    {
        $minas = Mina::get_minas($id_concesion);
        return ApiResponse::success($minas);
    }

    /**
     * Crear mina.
     */
    public function crear_mina(int $id_concesion, string $nombre, ?string $descripcion)
    {
        // 1. Crear
        $id_mina = Mina::crear_mina($id_concesion, $nombre, $descripcion);
        
        // 2. Retornar objeto completo (Optimistic UI)
        $mina = Mina::get_mina_by_id($id_mina);
        
        return ApiResponse::success($mina, 'Mina creada correctamente');
    }

    /**
     * Actualizar mina.
     */
    public function update_mina(int $id, int $id_concesion, string $nombre, ?string $descripcion)
    {
        Mina::update_mina($id, $id_concesion, $nombre, $descripcion);
        return ApiResponse::success(['mensaje' => 'Mina actualizada correctamente']);
    }

    /**
     * Eliminar mina.
     */
    public function delete_mina(int $id)
    {
        Mina::delete_mina($id);
        return ApiResponse::success(['mensaje' => 'Mina eliminada correctamente']);
    }

    // --- RELACIÓN EMPRESA_MINA ---

    /**
     * Asignar empresa a mina.
     */
    /**
     * Asignar empresa a mina.
     * REGLA DE NEGOCIO: La empresa debe tener un CONTRATO VIGENTE en la concesión a la que pertenece la mina.
     */
    public function asignar_empresa_mina(int $id_mina, int $id_empresa)
    {
        // 1. Obtener la mina para saber su concesion
        $mina = Mina::get_mina_by_id($id_mina);
        if (!$mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        // 2. Verificar duplicados
        if (Mina::verificar_empresa_asignada($id_mina, $id_empresa)) {
            return ApiResponse::error('La empresa ya está asignada a esta mina.');
        }

        // 3. VALIDAR CONTRATO VIGENTE en CONCESIÓN
        if (!Mina::check_contrato_vigente($mina->id_concesion, $id_empresa)) {
            return ApiResponse::error('La empresa NO TIENE un contrato vigente en la concesión de esta mina.');
        }

        $id = Mina::asignar_empresa($id_mina, $id_empresa);
        
        return ApiResponse::success(['id_asignacion' => $id, 'mensaje' => 'Empresa asignada correctamente']);
    }

    /**
     * Desasignar empresa de mina.
     */
    public function desasignar_empresa_mina(int $id_asignacion)
    {
        Mina::desasignar_empresa($id_asignacion);
        return ApiResponse::success(null, 'Asignación eliminada correctamente');
    }

    /**
     * Listar empresas asignadas a una mina.
     */
    public function get_empresas_mina(int $id_mina)
    {
        $empresas = Mina::get_empresas_asignadas($id_mina);
        return ApiResponse::success($empresas);
    }
}
