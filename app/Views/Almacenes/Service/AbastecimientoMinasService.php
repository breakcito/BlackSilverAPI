<?php

namespace App\Views\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Data\AbastecimientoMinasData;

class AbastecimientoMinasService
{
    public function __construct(
        private AbastecimientoMinasData $data,
    ) {}

    /**
     * Asignar nueva mina por abastecer
     * @param int $id_almacen
     * @param int $id_mina
     */
    public function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        if ($this->data->verificar_abastecimiento_mina($id_almacen, $id_mina)) {
            return ApiResponse::error('Esta mina ya está siendo abastecida por este almacén.');
        }

        $id = $this->data->nueva_mina_por_abastecer($id_almacen, $id_mina);

        $nuevaAsignacion = $this->data->get_mina_abastecida_by_id($id);

        return ApiResponse::success($nuevaAsignacion, 'Mina asignada correctamente');
    }

    /**
     * Dejar de abastecer a una mina
     * @param int $id_asignacion
     */
    public function eliminar_abastecimiento_mina(int $id_mina_almacen)
    {
        $this->data->eliminar_abastecimiento_mina($id_mina_almacen);
        return ApiResponse::success(null, 'Se detuvo el abastecimiento de esta mina');
    }

    /**
     * Listar las minas que abstece un almacen
     * @param int $id_almacen
     */
    public function get_minas_abastecidas(int $id_almacen)
    {
        $result = $this->data->get_minas_abastecidas($id_almacen);
        return ApiResponse::success($result);
    }

    /**
     * Listar todas las minas posibles para abastecer
     * @param int $id_almacen
     */
    public function get_minas(int $id_almacen)
    {
        $result = $this->data->get_minas($id_almacen);
        return ApiResponse::success($result);
    }
}
