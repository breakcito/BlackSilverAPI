<?php

namespace App\Views\Almacenes;

use App\Shared\Responses\ApiResponse;

class AlmacenesService
{
    public function __construct(
        private AlmacenesData $data,
    ) {}

    /**
     * Listar un resumen de todos los almacenes
     */
    public function get_almacenes()
    {
        $almacenes = AlmacenesData::get_almacenes();
        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un nuevo almacén.
     * @param string $nombre
     * @param mixed $descripcion
     * @param bool $es_principal
     */
    public function crear_almacen(string $nombre, ?string $descripcion = null, bool $es_principal)
    {
        if (AlmacenesData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = AlmacenesData::crear_almacen($nombre, $descripcion, $es_principal);
        $nuevoAlmacen = AlmacenesData::get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }

    /**
     * Asignar un nuevo responsable de almacen
     * @param int $id_almacen
     * @param int $id_empleado
     * @param string $fecha_inicio
     */
    public function nuevo_responsable(int $id_almacen, int $id_empleado, string $fecha_inicio)
    {
        // Finalizar el periodo de actividad de los responsables anteriores
        AlmacenesData::update_fecha_fin_responsabilidad($id_almacen, $fecha_inicio);

        // Crear nuevo usando el id de la tabla empleado
        $id_nuevo_responsable = AlmacenesData::nuevo_responsable($id_almacen, $id_empleado, $fecha_inicio);
        $nuevoResponsable = AlmacenesData::get_responsable_by_id($id_nuevo_responsable);

        return ApiResponse::success($nuevoResponsable, 'Responsable asignado correctamente');
    }

    /**
     * Obtener historial de responsables de un almacen
     * @param int $id_almacen
     */
    public function get_historial_responsables(int $id_almacen)
    {
        $historial = AlmacenesData::get_historial_responsables($id_almacen);
        return ApiResponse::success($historial);
    }

    /**
     * Asignar nueva mina por abastecer
     * @param int $id_almacen
     * @param int $id_mina
     */
    public function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        if (AlmacenesData::verificar_abastecimiento_mina($id_almacen, $id_mina)) {
            return ApiResponse::error('Esta mina ya está siendo abastecida por este almacén.');
        }

        $id = AlmacenesData::nueva_mina_por_abastecer($id_almacen, $id_mina);

        $nuevaAsignacion = AlmacenesData::get_mina_abastecida_by_id($id);

        return ApiResponse::success($nuevaAsignacion, 'Mina asignada correctamente');
    }

    /**
     * Dejar de abastecer a una mina
     * @param int $id_asignacion
     */
    public function eliminar_abastecimiento_mina(int $id_mina_almacen)
    {
        AlmacenesData::eliminar_abastecimiento_mina($id_mina_almacen);
        return ApiResponse::success(true, 'Se detuvo el abastecimiento de esta mina');
    }

    /**
     * Listar las minas que abstece un almacen
     * @param mixed $id_almacen
     */
    public function get_minas_abastecidas(int $id_almacen)
    {
        return $this->data->get_minas_abastecidas($id_almacen);
    }
}
