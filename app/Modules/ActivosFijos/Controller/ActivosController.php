<?php

namespace App\Modules\ActivosFijos\Controller;

use App\Modules\ActivosFijos\Service\ActivosService;
use App\Services\ActivosFijosService as GlobalActivosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class ActivosController extends Controller
{
    /**
     * Obtener el listado de activos fijos con filtros opcionales.
     */
    public function get_activos(Request $request)
    {
        return ActivosService::get_activos();
    }

    /**
     * Registrar un nuevo activo fijo.
     */
    public function crear_activo(Request $request)
    {
        $id_producto = $request->integer('id_producto');
        $id_almacen = $request->input('id_almacen');
        $id_mina = $request->input('id_mina');
        $id_marca = $request->input('id_marca');
        $codigo = $request->input('codigo');
        $numero_serie = $request->input('numero_serie');
        $modelo = $request->input('modelo');
        $yearcito_modelo = $request->input('yearcito_modelo');
        $descripcion = $request->input('descripcion');
        $serie_placa = $request->input('serie_placa');
        $numero_placa = $request->input('numero_placa');
        $especificaciones = $request->input('especificaciones');
        $fecha_hora_ingreso = $request->input('fecha_hora_ingreso');
        $fecha_hora_ingreso = Carbon::parse($fecha_hora_ingreso)->toDateTimeString();
        $estado = $request->input('estado') ? EstadoActivoFijo::from($request->input('estado')) : null;

        return ActivosService::crear_activo(
            id_producto: $id_producto,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            id_marca: $id_marca,
            codigo: $codigo,
            numero_serie: $numero_serie,
            modelo: $modelo,
            yearcito_modelo: $yearcito_modelo,
            descripcion: $descripcion,
            serie_placa: $serie_placa,
            numero_placa: $numero_placa,
            especificaciones: $especificaciones,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            estado: $estado
        );
    }

    /**
     * Actualizar la ubicación de un activo fijo.
     */
    public function actualizar_ubicacion(Request $request)
    {
        $id_activo = $request->integer('id_activo');
        $tipo_movimiento = $request->input('tipo_movimiento');
        $tipo_movimiento = $tipo_movimiento
            ? MovimientoActivoFijo::from($tipo_movimiento)
            : null;
        $id_almacen = $request->input('id_almacen');
        $id_mina = $request->input('id_mina');
        $descripcion = $request->input('descripcion');
        $fecha_hora_movimiento = $request->input('fecha_hora_movimiento');

        return ActivosService::actualizar_ubicacion(
            id_activo: $id_activo,
            tipo_movimiento: $tipo_movimiento,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            descripcion: $descripcion,
            fecha_hora_movimiento: $fecha_hora_movimiento
        );
    }

    public function configurar_alertas(Request $request)
    {
        $id_activo = $request->integer('id_activo');
        $intervalo_horas = $request->input('intervalo_horas');
        $intervalo_kilometros = $request->input('intervalo_kilometros');
        $intervalo_vueltas = $request->input('intervalo_vueltas');

        return GlobalActivosService::configurar_alertas(
            id_activo: $id_activo,
            intervalo_horas: $intervalo_horas !== null ? (float) $intervalo_horas : null,
            intervalo_kilometros: $intervalo_kilometros !== null ? (float) $intervalo_kilometros : null,
            intervalo_vueltas: $intervalo_vueltas !== null ? (float) $intervalo_vueltas : null
        );
    }

    public function registrar_mantenimiento(Request $request)
    {
        $id_activo = $request->integer('id_activo');
        $id_empleado = $request->integer('id_empleado_registro');
        $tipo_control = $request->input('tipo_control'); // horometro, odometro, vueltas
        $observacion = $request->input('observacion');

        return GlobalActivosService::registrar_mantenimiento(
            id_activo: $id_activo,
            id_empleado: $id_empleado,
            tipo_control: $tipo_control,
            observacion: $observacion
        );
    }
}
