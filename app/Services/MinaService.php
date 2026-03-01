<?php

namespace App\Services;

use App\Models\EmpresaMina;
use App\Models\Mina;
use App\Models\ResponsableMina;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

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
        if (! $mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        // 2. Verificar duplicados
        if (EmpresaMina::verificar_empresa_asignada($id_mina, $id_empresa)) {
            return ApiResponse::error('La empresa ya está asignada a esta mina.');
        }

        // 3. VALIDAR CONTRATO VIGENTE en CONCESIÓN
        if (! Mina::check_contrato_vigente($mina->id_concesion, $id_empresa)) {
            return ApiResponse::error('La empresa NO TIENE un contrato vigente en la concesión de esta mina.');
        }

        $id = EmpresaMina::asignar_empresa($id_mina, $id_empresa);

        return ApiResponse::success(['id_asignacion' => $id, 'mensaje' => 'Empresa asignada correctamente']);
    }

    /**
     * Desasignar empresa de mina.
     */
    public function desasignar_empresa_mina(int $id_asignacion)
    {
        EmpresaMina::desasignar_empresa($id_asignacion);

        return ApiResponse::success(null, 'Asignación eliminada correctamente');
    }

    /**
     * Listar empresas asignadas a una mina.
     */
    public function get_empresas_mina(int $id_mina)
    {
        $empresas = EmpresaMina::get_empresas_asignadas($id_mina);

        return ApiResponse::success($empresas);
    }

    // --- RESPONSABLES DE MINA ---

    /**
     * Asignar responsable a mina.
     */
    public function asignar_responsable_mina(int $id_mina, int $id_usuario, string $fecha_inicio)
    {
        // 1. Obtener la mina
        $mina = Mina::get_mina_by_id($id_mina);
        if (! $mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        // 2. Validar si el usuario (vía sus empresas asociadas a esta mina) tiene autorización completa
        if (! Mina::check_usuario_autorizado_mina($id_usuario, $id_mina)) {
            return ApiResponse::error('Este usuario no pertenece a ninguna empresa autorizada en esta mina o no tiene contrato vigente.');
        }

        // 3. Transacción para cerrar anterior y crear nuevo
        DB::beginTransaction();
        try {
            ResponsableMina::cerrar_responsable_activo($id_mina, $fecha_inicio);
            ResponsableMina::asignar_responsable($id_mina, $id_usuario, $fecha_inicio, null);
            DB::commit();

            return ApiResponse::success(null, 'Responsable asignado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error('Error al asignar responsable: '.$e->getMessage());
        }
    }

    /**
     * Obtener usuarios autorizados para ser responsables de esta mina (PARA EL DROPDOWN DEL FRONT).
     */
    public function get_usuarios_autorizados(int $id_mina)
    {
        $usuarios = Mina::get_usuarios_autorizados($id_mina);

        return ApiResponse::success($usuarios);
    }

    /**
     * Obtener historial de responsables.
     */
    public function get_responsables_mina(int $id_mina)
    {
        $historial = ResponsableMina::get_responsables_historial($id_mina);

        return ApiResponse::success($historial);
    }
}
