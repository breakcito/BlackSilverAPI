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
        ?int $id_activo = null,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?int $id_producto = null,
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
            id_producto: $id_producto,
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
        ?array $especificaciones = null,
        ?string $fecha_hora_ingreso = null,
        ?EstadoActivoFijo $estado = EstadoActivoFijo::EnUso,
        //
        ?bool $return_objecto = false
    ) {
        return DB::transaction(function () use ($id_producto, $id_almacen, $id_mina, $id_marca, $codigo, $numero_serie, $modelo, $yearcito_modelo, $descripcion, $especificaciones, $fecha_hora_ingreso, $return_objecto, $estado) {
            $correlativo_data = ActivosFijosData::get_nuevo_correlativo();
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
            return ActivoFijoUbicacionLog::insertGetId([
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
        });
    }
}
