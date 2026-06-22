<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * ------------------------------------------------------
     * PARA LA CABECERA
     * ------------------------------------------------------
     */


    /**
     * Obtiene los requerimientos por almacén y periodo
     */
    public static function get_requerimientos(int $id_almacen, string $mes, string $yearcito)
    {
        $data = RequerimientosData::get_resumen_requerimientos($id_almacen, $mes, $yearcito);

        // Adjuntar labores a cada requerimiento
        foreach ($data as $req) {
            $req->evidencias = $req->evidencias ? json_decode($req->evidencias) : null;
            $req->labores = RequerimientosData::get_labores_by_requerimiento((int) $req->id_requerimiento);
        }

        return ApiResponse::success($data);
    }

    /**
     * Registrar un requerimiento de almacen desde el punto de vista del almacenero
     * labores: array de ids de labores
     * detalles: [
     *  {
     *   id_producto,
     *   id_unidad_medida,
     *   contenido_por_presentacion,
     *   cantidad_solicitada, // segun la unidad de medida del detalle (igual o diferente a la unidad base)
     *   comentario,
     *  }
     * ]
     */
    public static function registrar_requerimiento(
        ?int $id_empleado_solicitante,
        int $id_empleado_registro,
        ?int $id_mina,
        int $id_almacen_destino,
        bool $es_auditable,
        Premura $premura,
        array $detalles,
        ?array $labores = null,
        ?string $fecha_entrega_requerida = null,
        ?string $observacion = null,
        ?array $evidencias = null
    ) {
        return DB::transaction(function () use ($id_empleado_solicitante, $id_empleado_registro, $id_mina, $id_almacen_destino, $es_auditable, $premura, $observacion, $fecha_entrega_requerida, $labores, $detalles, $evidencias) {
            // 1. Generar correlativo
            $correlativo = RequerimientosData::get_nuevo_correlativo();

            // 2. Procesar evidencias
            $evidenciasFinal = null;
            if (!empty($evidencias)) {
                $evidenciasFinal = RequerimientosData::guardar_evidencias($evidencias);
            }

            // 3. Crear cabecera
            $id_requerimiento = RequerimientosData::crear_requerimiento(
                id_empleado_solicitante: $id_empleado_solicitante,
                id_empleado_registro: $id_empleado_registro,
                id_mina: $id_mina,
                id_almacen_destino: $id_almacen_destino,
                correlativo: $correlativo['correlativo'],
                numero_correlativo: $correlativo['numero_correlativo'],
                es_auditable: $es_auditable,
                premura: $premura,
                observacion: $observacion,
                fecha_entrega_requerida: $fecha_entrega_requerida,
                evidencias: $evidenciasFinal
            );

            // 3. Asociar Labores
            if (!empty($labores)) {
                foreach ($labores as $id_labor) {
                    RequerimientosData::asignar_labor($id_requerimiento, $id_labor);
                }
            }

            // 4. Crear Detalles y Trazabilidad
            foreach ($detalles as $detalle) {
                $contenido = (float) $detalle['contenido_por_presentacion'];
                $cantidad = (float) $detalle['cantidad_solicitada'];
                $cantidad_base = $cantidad * $contenido;

                $id_detalle = RequerimientosDetalleData::crear_detalle(
                    $id_requerimiento,
                    $detalle['id_producto'],
                    $detalle['id_unidad_medida'],
                    $cantidad,
                    $contenido,
                    $cantidad_base,
                    $detalle['comentario'] ?? null,
                    (bool) ($detalle['para_mantenimiento'] ?? false),
                    $detalle['id_activo_fijo_destino'] ?? null
                );

                RequerimientosDetalleData::registrar_trazabilidad($id_detalle, $id_empleado_registro);
            }

            // 5. Obtener resumen para el front
            $resumen = RequerimientosData::get_requerimiento_by_id($id_requerimiento);
            $resumen->evidencias = $resumen->evidencias ? json_decode($resumen->evidencias) : null;
            $resumen->labores = RequerimientosData::get_labores_by_requerimiento($id_requerimiento);
            $resumen->detalles = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);

            return ApiResponse::success(
                $resumen,
                'Requerimiento generado correctamente'
            );
        });
    }


    /**
     * ------------------------------------------------------
     * PARA EL DETALLE
     * ------------------------------------------------------
     */


    /**
     * Obtiene los detalles de un requerimiento
     */
    public static function get_detalles_requerimiento(int $id_requerimiento)
    {
        $data = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de uno o varios productos (Aprobado/Rechazado) y registra en Timeline.
     */
    public static function cambiar_estado_detalle(int $id_empleado, array $ids_detalles, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $ids_detalles, $nuevo_estado, $comentario_decision) {

            foreach ($ids_detalles as $id_detalle) {
                // 1. Actualizar el estado del detalle
                RequerimientosDetalleData::update_detalle_estado((int) $id_detalle, $nuevo_estado, $id_empleado, $comentario_decision);

                // 2. Determinar el Enum para el log
                $estadoEnum = EstadoRequerimientoDetalle::from($nuevo_estado);

                // 3. Colocar en estado de proceso al requerimiento si uno de sus detalles es aprobado
                if (EstadoRequerimientoDetalle::Aprobado->value === $nuevo_estado) {
                    $requerimiento = RequerimientosDetalleData::get_id_requerimiento_by_detalle((int) $id_detalle);
                    RequerimientosData::update_requerimiento_estado((int) $requerimiento->id_requerimiento_almacen, EstadoRequerimiento::EnDespacho->value);
                }

                $descripcion = $estadoEnum->getGlosa();
                RequerimientosDetalleData::insert_detalle_log(
                    (int) $id_detalle,
                    $id_empleado,
                    $comentario_decision ?? $descripcion,
                    EstadoRequerimientoDetalleLog::from($nuevo_estado)
                );
            }

            $mensaje = count($ids_detalles) > 1
                ? 'Estado de los productos actualizado correctamente'
                : 'Estado del producto actualizado correctamente';

            return ApiResponse::success(null, $mensaje);
        });
    }

    /**
     * Obtiene la trazabilidad de un detalle de requerimiento
     */
    public static function obtener_trazabilidad(int $id_detalle)
    {
        $data = RequerimientosDetalleData::get_detalle_logs($id_detalle);
        return ApiResponse::success($data);
    }
}
