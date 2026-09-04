<?php

namespace App\Modules\ActivosFijos\Controller;

use App\Modules\ActivosFijos\Service\ActivosService;
use App\Services\ActivosFijosService as GlobalActivosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
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
        $id_labor = $request->has('id_labor') ? $request->integer('id_labor') : null;
        $ids_labores_abastecidas = $request->input('ids_labores_abastecidas');
        if (is_string($ids_labores_abastecidas)) {
            // Permitir JSON como string por si el cliente lo manda serializado
            $decoded = json_decode($ids_labores_abastecidas, true);
            $ids_labores_abastecidas = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($ids_labores_abastecidas)) {
            $ids_labores_abastecidas = [];
        }
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
        if ($fecha_hora_ingreso) {
            $fecha_hora_ingreso = Carbon::parse($fecha_hora_ingreso)->setTimezone(config('app.timezone'))->toDateTimeString();
        }
        $estado = $request->input('estado') ? EstadoActivoFijo::from($request->input('estado')) : null;

        $id_empleado_responsable = $request->has('id_empleado_responsable') ? $request->integer('id_empleado_responsable') : null;
        $serie_factura_compra = $request->input('serie_factura_compra');
        $numero_factura_compra = $request->input('numero_factura_compra');
        $costo_compra = $request->has('costo_compra') ? $request->float('costo_compra') : null;

        // Procesar evidencias: si vienen como archivos, los guardamos en storage;
        // si ya vienen como JSON (IArchivo[]), las pasamos tal cual al servicio.
        $evidencias = null;
        if ($request->hasFile('evidencias')) {
            $archivos = $request->file('evidencias');
            if (!is_array($archivos)) {
                $archivos = [$archivos];
            }
            $evidencias = ArchivoHelper::guardarArchivos('activos_fijos', $archivos);
        } else {
            $evidenciasInput = $request->input('evidencias');
            if (is_string($evidenciasInput)) {
                $decoded = json_decode($evidenciasInput, true);
                $evidencias = is_array($decoded) ? $decoded : null;
            } elseif (is_array($evidenciasInput)) {
                $evidencias = $evidenciasInput;
            }
        }

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
            estado: $estado,
            id_empleado_responsable: $id_empleado_responsable,
            serie_factura_compra: $serie_factura_compra,
            numero_factura_compra: $numero_factura_compra,
            costo_compra: $costo_compra,
            id_labor: $id_labor,
            ids_labores_abastecidas: $ids_labores_abastecidas,
            evidencias: $evidencias
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
        if ($fecha_hora_movimiento) {
            $fecha_hora_movimiento = Carbon::parse($fecha_hora_movimiento)->setTimezone(config('app.timezone'))->toDateTimeString();
        }

        return ActivosService::actualizar_ubicacion(
            id_activo: $id_activo,
            tipo_movimiento: $tipo_movimiento,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            descripcion: $descripcion,
            fecha_hora_movimiento: $fecha_hora_movimiento
        );
    }

    /**
     * Editar un activo fijo existente.
     * Acepta cambios en metadata (codigo, modelo, serie, placa, descripcion,
     * yearcito_modelo, numero_serie, id_labor, estado, especificaciones) y
     * opcionalmente cambios de ubicación física (id_almacen, id_mina).
     * Si la ubicación cambió, el Service registra automáticamente el
     * movimiento correspondiente. El estado enviado por el usuario tiene
     * prioridad sobre el cálculo automático de new_ubicacion.
     * El Service calcula el diff entre estado previo y nuevo y lo apendea
     * a cambios_log (necesita id_empleado + nombre_empleado del auth_user).
     */
    public function actualizar_activo(Request $request, int $id_activo)
    {
        $codigo = $request->input('codigo');
        $numero_serie = $request->input('numero_serie');
        $modelo = $request->input('modelo');
        $yearcito_modelo = $request->has('yearcito_modelo') ? $request->integer('yearcito_modelo') : null;
        $descripcion = $request->input('descripcion');
        $serie_placa = $request->input('serie_placa');
        $numero_placa = $request->input('numero_placa');
        $id_labor = $request->has('id_labor') ? $request->integer('id_labor') : null;
        $estado = $request->input('estado');

        // Especificaciones: el cliente puede enviar array nativo, JSON string, o null.
        $especificaciones = null;
        if ($request->has('especificaciones')) {
            $espRaw = $request->input('especificaciones');
            if (is_string($espRaw)) {
                $decoded = json_decode($espRaw, true);
                $especificaciones = is_array($decoded) ? $decoded : null;
            } elseif (is_array($espRaw)) {
                $especificaciones = $espRaw;
            }
        }

        $id_almacen = $request->has('id_almacen') ? $request->integer('id_almacen') : null;
        $id_mina = $request->has('id_mina') ? $request->integer('id_mina') : null;
        $descripcion_ubicacion = $request->input('descripcion_ubicacion');

        // Construir $data con los campos que el usuario envió (no null).
        // `estado` puede ser null explícito (para "limpiar"), así que usamos
        // array_key_exists para distinguir "no enviado" de "null".
        $data = [];
        foreach ([
            'codigo' => $codigo,
            'numero_serie' => $numero_serie,
            'modelo' => $modelo,
            'yearcito_modelo' => $yearcito_modelo,
            'descripcion' => $descripcion,
            'serie_placa' => $serie_placa,
            'numero_placa' => $numero_placa,
            'id_labor' => $id_labor,
        ] as $key => $val) {
            if ($val !== null) {
                $data[$key] = $val;
            }
        }

        if ($request->has('estado')) {
            $data['estado'] = $estado; // puede ser null explícito
        }
        if ($request->has('especificaciones')) {
            $data['especificaciones'] = $especificaciones;
        }

        if (empty($data) && $id_almacen === null && $id_mina === null) {
            return ApiResponse::error('No se proporcionaron campos para actualizar.');
        }

        // Para el log de cambios (trazabilidad)
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        return ActivosService::actualizar_activo(
            id_activo: $id_activo,
            data: $data,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            descripcion_ubicacion: $descripcion_ubicacion,
            id_empleado: $idEmpleado,
            nombre_empleado: $nombreEmpleado,
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

    /**
     * Desactivar (soft delete) un activo fijo. Cambia estado a "Dado de Baja"
     * y registra la accion en cambios_log.
     */
    public function eliminar_activo(Request $request, int $id_activo)
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        $result = ActivosService::eliminar_activo(
            id_activo: $id_activo,
            id_empleado: $idEmpleado,
            nombre_empleado: $nombreEmpleado,
        );

        return response()->json($result);
    }

    public function registrar_mantenimiento(Request $request)
    {
        $id_activo = $request->integer('id_activo');
        $id_empleado = $request->integer('id_empleado_registro');
        $tipo_control = $request->input('tipo_control'); // horometro, odometro, vueltas
        $observacion = $request->input('observacion');
        $fecha_hora_mantenimiento = $request->input('fecha_hora_mantenimiento');

        return GlobalActivosService::registrar_mantenimiento(
            id_activo: $id_activo,
            id_empleado: $id_empleado,
            tipo_control: $tipo_control,
            observacion: $observacion,
            fecha_hora_mantenimiento: $fecha_hora_mantenimiento
        );
    }
}
