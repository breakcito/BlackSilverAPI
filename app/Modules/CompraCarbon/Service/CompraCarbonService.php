<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonData;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class CompraCarbonService
{
    public static function get_compras(array $opts = []): array
    {
        $data = CompraCarbonData::get_compras($opts);
        return ApiResponse::success($data, 'Compras de carbon obtenidas correctamente');
    }

    public static function get_compra_con_detalles(int $id_compra_carbon): array
    {
        $resultado = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($resultado['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        return ApiResponse::success($resultado, 'Compra obtenida correctamente');
    }

    /**
     * Aprueba una compra de carbon: setea id_empleado_aprueba,
     * fecha_hora_aprobacion (now) y estado = Aprobado.
     */
    public static function aprobar_compra(int $id_compra_carbon, int $id_empleado_aprueba): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Aprobado->value) {
            return ApiResponse::error('La compra ya fue aprobada');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('La compra esta anulada, no se puede aprobar');
        }

        CompraCarbonData::aprobar(
            $id_compra_carbon,
            $id_empleado_aprueba,
            now()->toDateTimeString(),
        );

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Guarda la lista de evidencias de aprobacion (reemplaza TODAS).
     * Solo permitido si la compra esta aprobada.
     *
     * @param array<int, array{url:string, path_relativo:string, nombre_original:?string, extension:?string}> $evidencias
     */
    public static function set_evidencias(int $id_compra_carbon, array $evidencias): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado !== EstadoCompraCarbon::Aprobado->value) {
            return ApiResponse::error('Solo se pueden subir evidencias a una compra aprobada');
        }

        CompraCarbonData::set_evidencias($id_compra_carbon, $evidencias);

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Anula una compra de carbon. Permitido desde Pendiente o Aprobado.
     * Si ya esta Anulada, retorna error.
     */
    public static function anular_compra(int $id_compra_carbon): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('La compra ya esta anulada');
        }

        CompraCarbonData::anular($id_compra_carbon);

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Crea una compra de carbon (cabecera + N detalles) en transaccion.
     * Genera el correlativo automatico y calcula el total.
     *
     * @param array $payload Esperado:
     *   - id_empresa (int)
     *   - id_proveedor (int)
     *   - porcentaje_igv (float)
     *   - fecha_hora_ingreso (string Y-m-d H:i:s)
     *   - detalles (array<id_tipo_carbon:int, cantidad:float, precio_unitario:float>)
     */
    public static function crear_compra(array $payload, int $id_empleado_registro): array
    {
        $id_empresa = (int) ($payload['id_empresa'] ?? 0);
        $id_proveedor = (int) ($payload['id_proveedor'] ?? 0);
        $porcentaje_igv = (float) ($payload['porcentaje_igv'] ?? 0);
        $fecha_hora_ingreso = (string) ($payload['fecha_hora_ingreso'] ?? '');
        /** @var array<int, array<string, mixed>> $detallesIn */
        $detallesIn = $payload['detalles'] ?? [];

        if ($id_empresa <= 0 || $id_proveedor <= 0) {
            return ApiResponse::error('Empresa y proveedor son requeridos');
        }
        if (empty($detallesIn)) {
            return ApiResponse::error('La compra debe tener al menos un item');
        }

        // Calculamos subtotales por linea y el total con IGV.
        $detallesNorm = [];
        $subtotalBase = 0.0;
        foreach ($detallesIn as $d) {
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $precio = (float) ($d['precio_unitario'] ?? 0);
            $id_tipo = (int) ($d['id_tipo_carbon'] ?? 0);
            if ($id_tipo <= 0 || $cantidad <= 0 || $precio < 0) {
                return ApiResponse::error('Todos los items deben tener tipo de carbon, cantidad > 0 y precio >= 0');
            }
            $sub = round($cantidad * $precio, 2);
            $subtotalBase += $sub;
            $detallesNorm[] = [
                'id_tipo_carbon' => $id_tipo,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $sub,
            ];
        }
        $total = round($subtotalBase * (1 + $porcentaje_igv / 100), 2);

        return DB::transaction(function () use ($id_empresa, $id_proveedor, $id_empleado_registro, $porcentaje_igv, $fecha_hora_ingreso, $detallesNorm, $total) {
            $correlativoData = CorrelativoHelper::generar(
                tabla: 'compra_carbon',
                prefijo: 'CC',
                filtros: ['id_empresa' => $id_empresa],
                longitudCeros: 5,
                reseteo: Periodo::Anual,
                columnaFecha: 'fecha_hora_ingreso',
            );

            $id_compra = CompraCarbonData::insert_cabecera([
                'id_empresa' => $id_empresa,
                'id_proveedor' => $id_proveedor,
                'id_empleado_registro' => $id_empleado_registro,
                'porcentaje_igv' => $porcentaje_igv,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'fecha_hora_ingreso' => $fecha_hora_ingreso,
                'total' => $total,
                'estado' => EstadoCompraCarbon::Pendiente->value,
                'created_at' => now()->toDateTimeString(),
            ]);

            CompraCarbonData::insert_detalles($id_compra, $detallesNorm);

            return self::get_compra_con_detalles($id_compra);
        });
    }
}