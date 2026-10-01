<?php

namespace App\Modules\ActivosFijos\Service;

use App\Modules\ActivosFijos\Data\ActivosData;
use App\Services\ActivosFijosService as GlobalActivosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ActivosService
{
    /**
     * Listar todos los activos fijos con su información detallada.
     */
    public static function get_activos()
    {
        $activos = ActivosData::get_activos();
        return ApiResponse::success($activos);
    }

    /**
     * Crear un nuevo activo fijo consumiendo el servicio global.
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
        // Nuevos
        ?int $id_empleado_responsable = null,
        ?string $serie_factura_compra = null,
        ?string $numero_factura_compra = null,
        ?float $costo_compra = null,
        ?int $id_labor = null,
        ?array $ids_labores_abastecidas = null,
        ?array $evidencias = null
    ) {
        $res = GlobalActivosService::crear_activo(
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
            return_objecto: false,
            // Nuevos
            id_empleado_responsable: $id_empleado_responsable,
            serie_factura_compra: $serie_factura_compra,
            numero_factura_compra: $numero_factura_compra,
            costo_compra: $costo_compra,
            id_labor: $id_labor,
            ids_labores_abastecidas: $ids_labores_abastecidas,
            evidencias: $evidencias
        );

        $id_activo = $res['data'];

        return ApiResponse::success(ActivosData::get_activos((int) $id_activo));
    }

    /**
     * Actualizar la ubicación de un activo fijo consumiendo el servicio global.
     */
    public static function actualizar_ubicacion(
        int $id_activo,
        MovimientoActivoFijo $tipo_movimiento,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?string $descripcion = null,
        ?string $fecha_hora_movimiento = null
    ) {
        $id_log = GlobalActivosService::new_ubicacion(
            id_activo: $id_activo,
            tipo_movimiento: $tipo_movimiento,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            descripcion: $descripcion,
            fecha_hora_movimiento: $fecha_hora_movimiento
        );

        return ApiResponse::success($id_log, 'Ubicación actualizada correctamente');
    }

    /**
     * Editar un activo fijo existente.
     * - Actualiza metadata (codigo, modelo, serie, placa, estado, especificaciones, etc.) + labor.
     * - Si cambió id_almacen o id_mina respecto al estado actual,
     *   registra un movimiento en activo_fijo_ubicacion_log con el
     *   MovimientoActivoFijo derivado de la transición.
     * - Calcula diff entre estado original y nuevo y lo apendea a cambios_log
     *   para trazabilidad (solo si recibe id_empleado + nombre_empleado).
     *
     * @param array $data Campos editables crudos del Request. El Service normaliza
     *                    el formato de `especificaciones` (json_encode si es array)
     *                    y valida que `estado`, si viene, sea un EstadoActivoFijo válido.
     *                    Para `estado` se respeta el valor enviado por el usuario
     *                    cuando NO hay cambio de ubicación. Si hay cambio de ubicación
     *                    Y el usuario envió `estado`, el `estado` enviado gana sobre
     *                    el cálculo automático de new_ubicacion.
     */
    public static function actualizar_activo(
        int $id_activo,
        array $data,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?string $descripcion_ubicacion = null,
        ?string $fecha_hora_movimiento = null,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        // Capturar el estado ORIGINAL completo (no solo ubicación) para el diff
        $originalFull = DB::table('activo_fijo')
            ->where('id', $id_activo)
            ->first();

        if (!$originalFull) {
            return ApiResponse::error('El activo que intenta editar no existe.');
        }

        // Snapshot para detectar cambio de ubicación (campo específico)
        $id_almacen_anterior = $originalFull->id_almacen !== null ? (int) $originalFull->id_almacen : null;
        $id_mina_anterior = $originalFull->id_mina !== null ? (int) $originalFull->id_mina : null;

        $hubo_cambio_ubicacion =
            $id_almacen_anterior !== $id_almacen
            || $id_mina_anterior !== $id_mina;

        // Normalizar `especificaciones` (json_encode si viene como array).
        // Array vacío o null → null en BD.
        if (array_key_exists('especificaciones', $data)) {
            $esp = $data['especificaciones'];
            if ($esp === null) {
                $data['especificaciones'] = null;
            } elseif (is_array($esp)) {
                $data['especificaciones'] = empty($esp) ? null : json_encode(array_values($esp));
            }
        }

        // `estado` puede venir como string (valor del enum) o como array.
        // Lo guardamos como string para que el cast del modelo lo maneje.
        // Solo se incluye si el usuario lo envió explícitamente.
        $estado_enviado_por_usuario = null;
        if (array_key_exists('estado', $data)) {
            $estadoValor = $data['estado'];
            if ($estadoValor !== null && $estadoValor !== '') {
                // Validar que sea un EstadoActivoFijo válido
                $estadoEnum = EstadoActivoFijo::tryFrom((string) $estadoValor);
                if ($estadoEnum === null) {
                    return ApiResponse::error('Estado de activo inválido.');
                }
                $data['estado'] = $estadoEnum->value;
                $estado_enviado_por_usuario = $estadoEnum->value;
            } else {
                $data['estado'] = null;
            }
        }

        return DB::transaction(function () use ($id_activo, $data, $originalFull, $id_almacen, $id_mina, $id_almacen_anterior, $id_mina_anterior, $hubo_cambio_ubicacion, $descripcion_ubicacion, $fecha_hora_movimiento, $estado_enviado_por_usuario, $id_empleado, $nombre_empleado) {
            // 1) Actualizar metadata (incluye id_labor, estado, especificaciones si vienen)
            ActivosData::actualizar_activo($id_activo, $data);

            // 2) Si cambió ubicación física (almacén/mina), registrar log
            if ($hubo_cambio_ubicacion) {
                $tipo_movimiento = self::derivar_movimiento(
                    id_almacen_anterior: $id_almacen_anterior,
                    id_mina_anterior: $id_mina_anterior,
                    id_almacen_nuevo: $id_almacen,
                    id_mina_nuevo: $id_mina
                );

                if ($tipo_movimiento !== null) {
                    GlobalActivosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: $tipo_movimiento,
                        id_almacen: $id_almacen,
                        id_mina: $id_mina,
                        descripcion: $descripcion_ubicacion ?? 'Edición de activo',
                        fecha_hora_movimiento: $fecha_hora_movimiento
                    );
                }
            }

            // 3) Si el usuario envió `estado` y new_ubicacion lo sobrescribió
            //    (porque new_ubicacion recalcula el estado al mover), re-aplicar
            //    el estado del usuario para que tenga prioridad sobre el cálculo
            //    automático.
            if ($hubo_cambio_ubicacion && $estado_enviado_por_usuario !== null) {
                ActivosData::actualizar_activo($id_activo, [
                    'estado' => $estado_enviado_por_usuario,
                ]);
            }

            // 4) Calcular diff entre estado original y nuevo y apendear a cambios_log
            //    SOLO si el cliente pasó id_empleado + nombre_empleado.
            if ($id_empleado !== null && $nombre_empleado !== null) {
                $nuevo = DB::table('activo_fijo')
                    ->where('id', $id_activo)
                    ->first();

                if ($nuevo !== null) {
                    $cambiosLog = ActivosData::calcularDiffCambiosActivo(
                        original: $originalFull,
                        nuevo: $nuevo,
                        id_empleado: $id_empleado,
                        nombre_empleado: $nombre_empleado,
                    );

                    // calcularDiffCambiosActivo devuelve el logPrevio + la nueva entrada
                    // si hay cambios. Si no hay cambios, devuelve el logPrevio sin cambios.
                    // Comparamos con el logPrevio original para saber si debemos persistir.
                    $logOriginalDecoded = json_decode(
                        $originalFull->cambios_log ?? '[]',
                        true,
                    );
                    $logOriginalCount = is_array($logOriginalDecoded) ? count($logOriginalDecoded) : 0;
                    if (count($cambiosLog) > $logOriginalCount) {
                        ActivosData::appendCambiosLog($id_activo, $cambiosLog);
                    }
                }
            }

            return ApiResponse::success(
                ActivosData::get_activos($id_activo),
                'Activo actualizado correctamente'
            );
        });
    }

    /**
     * Desactivar (soft delete) un activo fijo. Cambia estado a "Dado de Baja"
     * y registra la accion en cambios_log para trazabilidad.
     */
    public static function eliminar_activo(
        int $id_activo,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        $existe = ActivosData::get_activos(id_activo: $id_activo);
        if (!$existe) {
            return ApiResponse::error('El activo que intenta eliminar no existe.');
        }

        ActivosData::eliminar_activo(
            id_activo: $id_activo,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        return ApiResponse::success(
            ActivosData::get_activos(id_activo: $id_activo),
            'Activo eliminado correctamente',
        );
    }

    /**
     * Ajuste manual de los contadores de uso de un activo fijo.
     *
     * Solo modifica los campos cuyo valor realmente cambió (no pisa con
     * null los que el cliente no envió). Si ningún campo cambió respecto
     * al valor actual, devuelve success sin tocar la BD ni el log.
     *
     * IMPORTANTE: este endpoint NO recalcula `proxima_advertencia_X`. La
     * alerta es un valor "seteado" por `configurar_alertas` o por
     * `registrar_mantenimiento` (que es la única mecánica que la mueve),
     * y se mantiene estable hasta el próximo ciclo de mantenimiento. Si
     * se recalculara en cada edición del total, la alerta quedaría
     * "saltarina" ante cualquier corrección, perdiendo estabilidad.
     *
     * Caso de "reset" (ej. horómetro reemplazado, total=5000 → 0): el
     * operario registra mantenimiento después del reemplazo, lo que
     * fija la alerta al valor correcto (`nuevo_total + intervalo`).
     *
     * NO toca el historial de `control_uso_activo`. La trazabilidad del
     * ajuste queda registrada en `activo_fijo.cambios_log`.
     */
    public static function ajustar_totales_activo(
        int $id_activo,
        ?float $total_horas = null,
        ?float $total_kilometros = null,
        ?float $total_vueltas = null,
        ?string $motivo = null,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null,
    ) {
        $original = DB::table('activo_fijo')->where('id', $id_activo)->first();
        if (!$original) {
            return ApiResponse::error('El activo que intenta ajustar no existe.');
        }

        // Diff: solo los campos que efectivamente cambiaron.
        $cambios = [];
        $updatePayload = [];

        if ($total_horas !== null && (float) $original->total_horas !== $total_horas) {
            $cambios[] = [
                'campo_bd' => 'total_horas',
                'campo' => 'Total Horas',
                'valor_anterior' => $original->total_horas,
                'valor_nuevo' => $total_horas,
            ];
            $updatePayload['total_horas'] = $total_horas;
        }

        if ($total_kilometros !== null && (float) $original->total_kilometros !== $total_kilometros) {
            $cambios[] = [
                'campo_bd' => 'total_kilometros',
                'campo' => 'Total Kilómetros',
                'valor_anterior' => $original->total_kilometros,
                'valor_nuevo' => $total_kilometros,
            ];
            $updatePayload['total_kilometros'] = $total_kilometros;
        }

        if ($total_vueltas !== null && (float) $original->total_vueltas !== $total_vueltas) {
            $cambios[] = [
                'campo_bd' => 'total_vueltas',
                'campo' => 'Total Vueltas',
                'valor_anterior' => $original->total_vueltas,
                'valor_nuevo' => $total_vueltas,
            ];
            $updatePayload['total_vueltas'] = $total_vueltas;
        }

        if (empty($cambios)) {
            return ApiResponse::success(
                ActivosData::get_activos($id_activo),
                'No se realizaron cambios (los valores son iguales a los actuales).'
            );
        }

        return DB::transaction(function () use ($id_activo, $updatePayload, $cambios, $original, $motivo, $id_empleado, $nombre_empleado) {
            // 1) Aplicar solo los totales cambiados. La alerta NO se toca.
            DB::table('activo_fijo')
                ->where('id', $id_activo)
                ->update($updatePayload);

            // 2) Append al log de cambios.
            if ($id_empleado !== null && $nombre_empleado !== null) {
                $logPrevio = self::decodeCambiosLogLocal($original->cambios_log ?? null);
                $logPrevio[] = [
                    'id_empleado' => $id_empleado,
                    'nombre_empleado' => $nombre_empleado,
                    'motivo' => $motivo,
                    'update_at' => now()->toDateTimeString(),
                    'cambios' => $cambios,
                ];
                ActivosData::appendCambiosLog($id_activo, $logPrevio);
            }

            return ApiResponse::success(
                ActivosData::get_activos($id_activo),
                'Contadores ajustados correctamente'
            );
        });
    }

    /**
     * Decodifica el JSON de `cambios_log` con tolerancia a null / array
     * / string. Misma lógica que `ActivosData::decodeCambiosLog` pero
     * accesible desde este Service sin ampliar la API pública del Data.
     */
    private static function decodeCambiosLogLocal(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Deriva el MovimientoActivoFijo comparando ubicación anterior vs nueva.
     * Devuelve null si la transición no aplica para log (ej. misma ubicación).
     */
    private static function derivar_movimiento(
        ?int $id_almacen_anterior,
        ?int $id_mina_anterior,
        ?int $id_almacen_nuevo,
        ?int $id_mina_nuevo
    ): ?MovimientoActivoFijo {
        $estaba_en_almacen = $id_almacen_anterior !== null;
        $estaba_en_mina = $id_mina_anterior !== null;
        $va_a_almacen = $id_almacen_nuevo !== null;
        $va_a_mina = $id_mina_nuevo !== null;

        // Ninguno de los dos tenía ubicación previa y no hay nueva → sin movimiento
        if (!$estaba_en_almacen && !$estaba_en_mina && !$va_a_almacen && !$va_a_mina) {
            return null;
        }

        // Misma ubicación exacta → sin movimiento
        if ($estaba_en_almacen === $va_a_almacen && $estaba_en_mina === $va_a_mina) {
            // Pero si solo cambió el id (de un almacén a otro), sí es DeAlmacenAAlmacen
            if ($estaba_en_almacen && $va_a_almacen && $id_almacen_anterior !== $id_almacen_nuevo) {
                return MovimientoActivoFijo::DeAlmacenAAlmacen;
            }
            if ($estaba_en_mina && $va_a_mina && $id_mina_anterior !== $id_mina_nuevo) {
                return MovimientoActivoFijo::DeMinaAMina;
            }
            return null;
        }

        if ($estaba_en_almacen && $va_a_mina)
            return MovimientoActivoFijo::DeAlmacenAMina;
        if ($estaba_en_mina && $va_a_almacen)
            return MovimientoActivoFijo::DeMinaAAlmacen;

        return null;
    }
}
