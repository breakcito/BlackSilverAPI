<?php

namespace App\Services;

use App\Models\Mina;
use App\Models\ResponsableMina;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class ResponsableMinaService
{
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
        if (!ResponsableMina::check_usuario_autorizado_mina($id_usuario, $id_mina)) {
            return ApiResponse::error('Este usuario no pertenece a ninguna empresa autorizada en esta mina o no tiene contrato vigente.');
        }

        // 3. Transacción para cerrar anterior y crear nuevo
        ResponsableMina::where('id_mina', $id_mina)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_inicio,
                'estado' => EstadoBase::Inactivo->value,
            ]);

        ResponsableMina::insertGetId([
            'id_mina' => $id_mina,
            'id_usuario' => $id_usuario,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => null,
            'estado' => EstadoBase::Activo->value,
        ]);

        return ApiResponse::success(null, 'Responsable asignado correctamente');
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
