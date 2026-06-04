<?php

namespace App\Services;

use App\Data\ProductosData;
use App\Models\ActivoFijoUbicacionLog;
use App\Data\ActivosFijosData;
use App\Services\KardexProductosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ActivosFijosService
{
    /**
     * Obtener solo los activos fijos
     * que esten disponibles segun se requiere.
     */
    public static function get_activos_disponibles(
        int|array|null $ids_productos = null,
        ?int $id_activo = null,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        //
        ?bool $para_transporte = null,
        ?bool $control_por_odometro = null,
        ?bool $control_por_horometro = null,
        ?EstadoActivoFijo $estado = null
    ) {
        $activos = ActivosFijosData::get_activos_disponibles(
            id_activo: $id_activo,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            ids_productos: $ids_productos,
            para_transporte: $para_transporte,
            control_por_odometro: $control_por_odometro,
            control_por_horometro: $control_por_horometro,
            estado: $estado
        );
        return ApiResponse::success($activos);
    }

    /**
     * Crear una nueva categoría
     * $return_objecto: Por default es false y devuelve solo el ID, pero si es true devuelve el objeto creado
     */
    public static function crear_activo(
        int $id_producto,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?int $id_marca = null,
        //
        ?string $codigo = null,
        ?string $numero_serie = null,
        ?string $modelo = null,
        ?int $yearcito_modelo = null,
        ?string $descripcion = null,
        ?string $serie_placa = null,
        ?string $numero_placa = null,
        ?array $especificaciones = null,
        ?string $fecha_hora_ingreso = null,
        ?EstadoActivoFijo $estado = EstadoActivoFijo::EnUso,
        //
        ?bool $return_objecto = false
    ) {
        return DB::transaction(function () use ($id_producto, $id_almacen, $id_mina, $id_marca, $codigo, $numero_serie, $modelo, $yearcito_modelo, $descripcion, $serie_placa, $numero_placa, $especificaciones, $fecha_hora_ingreso, $return_objecto, $estado) {
            $producto = ProductosData::get_producto_by_id(id_producto: $id_producto, columnas: ['prefijo']);
            $prefijo = $producto['prefijo'];

            $correlativo_data = ActivosFijosData::get_nuevo_correlativo($prefijo);
            $correlativo = $correlativo_data['correlativo'];
            $numero_correlativo = $correlativo_data['numero_correlativo'];

            $id_nuevo_activo = ActivosFijosData::crear_activo(
                id_producto: $id_producto,
                correlativo: $correlativo,
                numero_correlativo: $numero_correlativo,
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
            );

            // registrar su ubicacion
            self::new_ubicacion(
                id_activo: $id_nuevo_activo,
                tipo_movimiento: MovimientoActivoFijo::NuevoActivo,
                id_almacen: $id_almacen,
                id_mina: $id_mina,
                descripcion: $descripcion,
                fecha_hora_movimiento: $fecha_hora_ingreso
            );

            if ($return_objecto) {
                $nuevo_activo = ActivosFijosData::get_activos_disponibles(id_activo: $id_nuevo_activo);
                return ApiResponse::success($nuevo_activo, 'Activo fijo creado correctamente');
            }

            return ApiResponse::success($id_nuevo_activo, 'Activo fijo creado correctamente');
        });
    }


    /**
     * Logica para actualizar la ubicacion de un activo fijo
     * Debe recibir al menos un id_almacen o un id_mina o ninguno de ellos en caso se de de baja
     */
    public static function new_ubicacion(
        int $id_activo,
        MovimientoActivoFijo $tipo_movimiento,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?string $descripcion = null,
        //
        ?string $fecha_hora_movimiento = null
    ) {
        return DB::transaction(function () use ($id_activo, $tipo_movimiento, $id_almacen, $id_mina, $descripcion, $fecha_hora_movimiento) {
            // obtener datos del activo
            $activo_fijo = ActivosFijosData::get_activo_by_id(id_activo: $id_activo, columnas: ['id_producto']);
            $id_producto = $activo_fijo['id_producto'];
            $costo_promedio_base = ProductosData::get_costo_promedio_producto($id_producto);

            // si esta saliendo de un almacen, registramos la salida en su kardex
            if ($tipo_movimiento == MovimientoActivoFijo::DeAlmacenAMina || $tipo_movimiento == MovimientoActivoFijo::DeAlmacenAAlmacen) {
                KardexProductosService::registrar_kardex(
                    tipo_movimiento: KardexTipoMovimiento::Salida,
                    tipo_origen: KardexOrigenMovimiento::MovimientoInterno,
                    descripcion: $descripcion ?? 'Salida de activo fijo de almacen',
                    cantidad_movimiento: 1,
                    cantidad_movimiento_base: 1,
                    nuevo_stock: 0,
                    nuevo_stock_base: 0,
                    id_lote: null,
                    id_activo_fijo: $id_activo,
                    id_origen: null,
                    tabla_origen: null,
                    stock_anterior: 1,
                    stock_anterior_base: 1,
                    costo_promedio_base: $costo_promedio_base,
                    created_at: $fecha_hora_movimiento
                );
            }
            // si esta ingresando a un almacen, registramos el ingreso en su kardex
            // o si es un nuevo activo y esta entrando a un almacen,registramos el ingreso en su kardex
            else if (
                $tipo_movimiento == MovimientoActivoFijo::DeAlmacenAAlmacen ||
                $tipo_movimiento == MovimientoActivoFijo::DeMinaAAlmacen ||
                ($tipo_movimiento == MovimientoActivoFijo::NuevoActivo && $id_almacen != null) // si es un nuevo activo ingresando a un almacen
            ) {
                KardexProductosService::registrar_kardex(
                    tipo_movimiento: KardexTipoMovimiento::Ingreso,
                    tipo_origen: KardexOrigenMovimiento::MovimientoInterno,
                    descripcion: $descripcion ?? 'Ingreso de activo fijo a almacen',
                    cantidad_movimiento: 1,
                    cantidad_movimiento_base: 1,
                    nuevo_stock: 1,
                    nuevo_stock_base: 1,
                    id_lote: null,
                    id_activo_fijo: $id_activo,
                    id_origen: null,
                    tabla_origen: null,
                    stock_anterior: 0,
                    stock_anterior_base: 0,
                    costo_promedio_base: $costo_promedio_base,
                    created_at: $fecha_hora_movimiento
                );
            }

            // registramos la nueva ubicacion
            $id_log = ActivoFijoUbicacionLog::insertGetId([
                'id_activo_fijo' => $id_activo,
                'id_almacen' => $id_almacen,
                'id_mina' => $id_mina,
                //
                'descripcion' => $descripcion,
                //
                'tipo_movimiento' => $tipo_movimiento->value,
                //
                'fecha_hora_movimiento' => $fecha_hora_movimiento ?? now(),
                //
                'created_at' => now()
            ]);

            // determinamos el estado
            $estado = EstadoActivoFijo::EnAlmacen;
            if ($id_mina != null) {
                $estado = EstadoActivoFijo::EnUso;
            }

            // actualizamos la tabla principal
            ActivosFijosData::update_ubicacion($id_activo, $id_almacen, $id_mina, $estado);

            return $id_log;
        });
    }

    public static function configurar_alertas(
        int $id_activo,
        ?float $intervalo_horas,
        ?float $intervalo_kilometros,
        ?float $intervalo_vueltas
    ) {
        $activo = ActivosFijosData::get_activo_by_id($id_activo, ['id', 'total_horas', 'total_kilometros', 'total_vueltas', 'intervalo_mantenimiento_horas', 'intervalo_mantenimiento_kilometros', 'intervalo_mantenimiento_vueltas']);
        if (!$activo) {
            throw new \Exception('Activo no encontrado');
        }

        $update_data = [
            'intervalo_mantenimiento_horas' => $intervalo_horas,
            'intervalo_mantenimiento_kilometros' => $intervalo_kilometros,
            'intervalo_mantenimiento_vueltas' => $intervalo_vueltas,
        ];

        // Si se configura un intervalo por primera vez (o se cambia), recalcular la próxima advertencia
        // usando el total actual + el nuevo intervalo. Si el intervalo viene en null, la advertencia pasa a null
        if ($intervalo_horas !== null) {
            $update_data['proxima_advertencia_horas'] = ($activo['total_horas'] ?? 0) + $intervalo_horas;
        } else {
            $update_data['proxima_advertencia_horas'] = null;
        }

        if ($intervalo_kilometros !== null) {
            $update_data['proxima_advertencia_kilometros'] = ($activo['total_kilometros'] ?? 0) + $intervalo_kilometros;
        } else {
            $update_data['proxima_advertencia_kilometros'] = null;
        }

        if ($intervalo_vueltas !== null) {
            $update_data['proxima_advertencia_vueltas'] = ($activo['total_vueltas'] ?? 0) + $intervalo_vueltas;
        } else {
            $update_data['proxima_advertencia_vueltas'] = null;
        }

        ActivosFijosData::actualizar_config_alertas($id_activo, $update_data);
        return ApiResponse::success(null, 'Alertas configuradas correctamente');
    }

    public static function registrar_mantenimiento(
        int $id_activo,
        int $id_empleado,
        string $tipo_control,
        ?string $observacion,
        ?string $fecha_hora_mantenimiento = null
    ) {
        $activo = ActivosFijosData::get_activo_by_id($id_activo, ['id', 'total_horas', 'total_kilometros', 'total_vueltas', 'intervalo_mantenimiento_horas', 'intervalo_mantenimiento_kilometros', 'intervalo_mantenimiento_vueltas']);
        if (!$activo) {
            throw new \Exception('Activo no encontrado');
        }

        $log_data = [
            'id_activo_fijo' => $id_activo,
            'id_empleado_registro' => $id_empleado,
            'fecha_hora_mantenimiento' => $fecha_hora_mantenimiento ?? now(),
            'tipo_control' => $tipo_control,
            'observacion' => $observacion,
            'created_at' => now()
        ];

        $update_activo = [];

        // Dependiendo del tipo de control resuelto, guardamos el log y recalculamos el umbral para el próximo
        if ($tipo_control === 'horometro') {
            $log_data['valor_control'] = $activo['total_horas'];
            if ($activo['intervalo_mantenimiento_horas']) {
                $update_activo['proxima_advertencia_horas'] = $activo['total_horas'] + $activo['intervalo_mantenimiento_horas'];
            }
        } elseif ($tipo_control === 'odometro') {
            $log_data['valor_control'] = $activo['total_kilometros'];
            if ($activo['intervalo_mantenimiento_kilometros']) {
                $update_activo['proxima_advertencia_kilometros'] = $activo['total_kilometros'] + $activo['intervalo_mantenimiento_kilometros'];
            }
        } elseif ($tipo_control === 'vueltas') {
            $log_data['valor_control'] = $activo['total_vueltas'];
            if ($activo['intervalo_mantenimiento_vueltas']) {
                $update_activo['proxima_advertencia_vueltas'] = $activo['total_vueltas'] + $activo['intervalo_mantenimiento_vueltas'];
            }
        }

        ActivosFijosData::registrar_mantenimiento($log_data, $id_activo, $update_activo);

        return ApiResponse::success(null, 'Mantenimiento registrado y alerta reprogramada');
    }
}
