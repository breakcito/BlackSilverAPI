<?php

namespace App\Views\Almacenes;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Data\AbastecimientoMinasData;
use App\Views\Almacenes\Data\AlmacenesData;
use App\Views\Almacenes\Data\ResponsablesData;

class AlmacenesService
{
    public static function get_almacenes()
    {
        $almacenes = AlmacenesData::get_almacenes();

        return ApiResponse::success($almacenes);
    }

    public static function crear_almacen(string $nombre, ?string $descripcion = null, bool $es_principal)
    {
        if (AlmacenesData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = AlmacenesData::crear_almacen($nombre, $descripcion, $es_principal);
        $nuevoAlmacen = AlmacenesData::get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }

    //

    public static function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        if (AbastecimientoMinasData::verificar_abastecimiento_mina($id_almacen, $id_mina)) {
            return ApiResponse::error('Esta mina ya está siendo abastecida por este almacén.');
        }

        $id = AbastecimientoMinasData::nueva_mina_por_abastecer($id_almacen, $id_mina);

        $nuevaAsignacion = AbastecimientoMinasData::get_mina_abastecida_by_id($id);

        return ApiResponse::success($nuevaAsignacion, 'Mina asignada correctamente');
    }

    public static function eliminar_abastecimiento_mina(int $id_almacen_mina)
    {
        AbastecimientoMinasData::eliminar_abastecimiento_mina($id_almacen_mina);

        return ApiResponse::success(null, 'Se detuvo el abastecimiento de esta mina');
    }

    public static function get_minas_abastecidas(int $id_almacen)
    {
        $result = AbastecimientoMinasData::get_minas_abastecidas($id_almacen);

        return ApiResponse::success($result);
    }

    public static function get_minas(int $id_almacen)
    {
        $result = AbastecimientoMinasData::get_minas($id_almacen);

        return ApiResponse::success($result);
    }

    //

    public static function nuevo_responsable(int $id_almacen, int $id_empleado, string $fecha_inicio)
    {
        // Finalizar el periodo de actividad de los responsables anteriores
        ResponsablesData::update_fecha_fin_responsabilidad($id_almacen, $fecha_inicio);

        // Crear nuevo usando el id de la tabla empleado
        $id_nuevo_responsable = ResponsablesData::nuevo_responsable($id_almacen, $id_empleado, $fecha_inicio);
        $nuevoResponsable = ResponsablesData::get_responsable_by_id($id_nuevo_responsable);

        return ApiResponse::success($nuevoResponsable, 'Responsable asignado correctamente');
    }

    public static function get_historial_responsables(int $id_almacen)
    {
        $historial = ResponsablesData::get_historial_responsables($id_almacen);

        return ApiResponse::success($historial);
    }

    public static function get_empleados(int $id_almacen)
    {
        $result = ResponsablesData::get_empleados($id_almacen);

        return ApiResponse::success($result);
    }
}
