<?php

namespace App\Views\Almacenes;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Data\AbastecimientoMinasData;
use App\Views\Almacenes\Data\AlmacenesData;
use App\Views\Almacenes\Data\ResponsablesData;

class AlmacenesService
{
    public function __construct(
        private AlmacenesData $almacenesData,
        private AbastecimientoMinasData $abastecimientoMinasData,
        private ResponsablesData $responsablesData,
    ) {}

    /**
     * Listar un resumen de todos los almacenes
     */
    public function get_almacenes()
    {
        $almacenes = $this->almacenesData->get_almacenes();
        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un nuevo almacén.
     */
    public function crear_almacen(string $nombre, ?string $descripcion = null, bool $es_principal)
    {
        if ($this->almacenesData->verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = $this->almacenesData->crear_almacen($nombre, $descripcion, $es_principal);
        $nuevoAlmacen = $this->almacenesData->get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }


    //


    /**
     * Asignar nueva mina por abastecer
     */
    public function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        if ($this->abastecimientoMinasData->verificar_abastecimiento_mina($id_almacen, $id_mina)) {
            return ApiResponse::error('Esta mina ya está siendo abastecida por este almacén.');
        }

        $id = $this->abastecimientoMinasData->nueva_mina_por_abastecer($id_almacen, $id_mina);

        $nuevaAsignacion = $this->abastecimientoMinasData->get_mina_abastecida_by_id($id);

        return ApiResponse::success($nuevaAsignacion, 'Mina asignada correctamente');
    }

    /**
     * Dejar de abastecer a una mina
     */
    public function eliminar_abastecimiento_mina(int $id_mina_almacen)
    {
        $this->abastecimientoMinasData->eliminar_abastecimiento_mina($id_mina_almacen);
        return ApiResponse::success(null, 'Se detuvo el abastecimiento de esta mina');
    }

    /**
     * Listar las minas que abstece un almacen
     */
    public function get_minas_abastecidas(int $id_almacen)
    {
        $result = $this->abastecimientoMinasData->get_minas_abastecidas($id_almacen);
        return ApiResponse::success($result);
    }

    /**
     * Listar todas las minas posibles para abastecer
     */
    public function get_minas(int $id_almacen)
    {
        $result = $this->abastecimientoMinasData->get_minas($id_almacen);
        return ApiResponse::success($result);
    }


    //


    /**
     * Asignar un nuevo responsable de almacen
     */
    public function nuevo_responsable(int $id_almacen, int $id_empleado, string $fecha_inicio)
    {
        // Finalizar el periodo de actividad de los responsables anteriores
        $this->responsablesData->update_fecha_fin_responsabilidad($id_almacen, $fecha_inicio);

        // Crear nuevo usando el id de la tabla empleado
        $id_nuevo_responsable = $this->responsablesData->nuevo_responsable($id_almacen, $id_empleado, $fecha_inicio);
        $nuevoResponsable = $this->responsablesData->get_responsable_by_id($id_nuevo_responsable);

        return ApiResponse::success($nuevoResponsable, 'Responsable asignado correctamente');
    }

    /**
     * Obtener historial de responsables de un almacen
     */
    public function get_historial_responsables(int $id_almacen)
    {
        $historial = $this->responsablesData->get_historial_responsables($id_almacen);
        return ApiResponse::success($historial);
    }


    /**
     * Obtener listado de empleados para asignar como responsable de almacen
     */
    public function get_empleados(int $id_almacen)
    {
        $result = $this->responsablesData->get_empleados($id_almacen);
        return ApiResponse::success($result);
    }
}
