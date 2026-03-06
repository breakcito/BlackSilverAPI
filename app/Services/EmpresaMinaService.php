<?php

namespace App\Services;

use App\Models\ContratoConcesion;
use App\Models\EmpresaMina;
use App\Models\Mina;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class EmpresaMinaService
{
    public function asignar_empresa_mina(int $id_mina, int $id_empresa)
    {
        // Obtener la mina para saber su concesion
        $mina = Mina::where('id', $id_mina)->first(['id_concesion']);
        if (!$mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        // Verificar duplicados
        if (EmpresaMina::where('id_mina', $id_mina)->where('id_empresa', $id_empresa)->exists()) {
            return ApiResponse::error('La empresa ya está asignada a esta mina.');
        }

        // VALIDAR CONTRATO VIGENTE en CONCESIÓN
        if (!ContratoConcesion::where('id_concesion', $mina->id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
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
