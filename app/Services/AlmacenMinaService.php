<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\AlmacenMina;
use App\Shared\Responses\ApiResponse;

class AlmacenMinaService
{
    /**
     * Asignar una mina a abastecer
     */
    public function asignar_mina_almacen(int $id_almacen, int $id_mina)
    {
        if (AlmacenMina::where('id_almacen', $id_almacen)->where('id_mina', $id_mina)->exists()) {
            return ApiResponse::error('Esta mina ya está asignada al almacén.');
        }

        $almacenMina = AlmacenMina::create([
            'id_almacen' => $id_almacen,
            'id_mina' => $id_mina,
        ]);

        return ApiResponse::success(['id_asignacion' => $almacenMina->id], 'Mina asignada correctamente');
    }

    /**
     * Listar minas que abastece un almacen
     */
    public function get_minas_almacen(int $id_almacen)
    {
        $minas = AlmacenMina::get_minas_asignadas($id_almacen);

        return ApiResponse::success($minas);
    }

    /**
     * Dejar de abastecer a una mina
     */
    public function desasignar_mina_almacen(int $id_asignacion)
    {
        AlmacenMina::where('id', $id_asignacion)->delete();

        return ApiResponse::success(null, 'Mina desvinculada del almacén correctamente');
    }

    public function get_almacenes_por_mina(int $id_mina)
    {
        $almacenes = Almacen::join('almacen_mina as am', 'am.id_almacen', '=', 'a.id')
            ->where('am.id_mina', $id_mina)
            ->where('a.estado', 'Activo')
            ->select('a.id', 'a.nombre', 'a.es_principal')
            ->get();

        return ApiResponse::success($almacenes);
    }
}
