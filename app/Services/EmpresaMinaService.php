<?php

namespace App\Services;

use App\Models\EmpresaMina;
use App\Models\Mina;
use App\Shared\Responses\ApiResponse;

class EmpresaMinaService
{
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
}
