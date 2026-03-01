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
        $mina = Mina::create([
            'id_concesion' => $id_concesion,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        return ApiResponse::success(Mina::get_mina_by_id($mina->id), 'Mina creada correctamente');
    }

    /**
     * Actualizar mina.
     */
    public function update_mina(int $id, int $id_concesion, string $nombre, ?string $descripcion)
    {
        Mina::where('id', $id)
            ->update([
                'id_concesion' => $id_concesion,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
            ]);

        return ApiResponse::success(['mensaje' => 'Mina actualizada correctamente']);
    }

    /**
     * Eliminar mina.
     */
    public function delete_mina(int $id)
    {
        Mina::where('id', $id)
            ->update(['estado' => \App\Shared\Enums\EstadoBase::Inactivo->value]);

        return ApiResponse::success(['mensaje' => 'Mina eliminada correctamente']);
    }

    // --- RELACIÓN EMPRESA_MINA ---

    public function asignar_empresa_mina(int $id_mina, int $id_empresa)
    {
        // 1. Obtener la mina para saber su concesion
        $mina = Mina::get_mina_by_id($id_mina);
        if (! $mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        // 2. Verificar duplicados
        if (EmpresaMina::where('id_mina', $id_mina)->where('id_empresa', $id_empresa)->exists()) {
            return ApiResponse::error('La empresa ya está asignada a esta mina.');
        }

        // 3. VALIDAR CONTRATO VIGENTE en CONCESIÓN
        if (! \App\Models\ContratoConcesion::where('id_concesion', $mina->id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)
            ->exists()) {
            return ApiResponse::error('La empresa NO TIENE un contrato vigente en la concesión de esta mina.');
        }

        $empresaMina = EmpresaMina::create([
            'id_mina' => $id_mina,
            'id_empresa' => $id_empresa,
        ]);

        return ApiResponse::success(['id_asignacion' => $empresaMina->id, 'mensaje' => 'Empresa asignada correctamente']);
    }

    /**
     * Desasignar empresa de mina.
     */
    public function desasignar_empresa_mina(int $id_asignacion)
    {
        EmpresaMina::where('id', $id_asignacion)->delete();

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
        if (! ResponsableMina::check_usuario_autorizado_mina($id_usuario, $id_mina)) {
            return ApiResponse::error('Este usuario no pertenece a ninguna empresa autorizada en esta mina o no tiene contrato vigente.');
        }

        // 3. Transacción para cerrar anterior y crear nuevo
        DB::beginTransaction();
        try {
            ResponsableMina::where('id_mina', $id_mina)
                ->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)
                ->update([
                    'fecha_fin' => $fecha_inicio,
                    'estado' => \App\Shared\Enums\EstadoBase::Inactivo->value,
                ]);

            ResponsableMina::insertGetId([
                'id_mina' => $id_mina,
                'id_usuario' => $id_usuario,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => null,
                'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
            ]);
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
