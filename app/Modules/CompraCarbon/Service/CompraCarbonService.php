<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonData;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class CompraCarbonService
{
    private const CARPETA_EVIDENCIAS_CARGAS = 'evidencias-cargas-carbon';

    /**
     * Lista compras con filtros.
     * @param array<string, mixed> $opts
     */
    public static function get_compras(array $opts = []): array
    {
        $rows = CompraCarbonData::get_compras($opts);
        return ApiResponse::success($rows);
    }

    /**
     * Obtiene una compra con sus detalles, cargas, comprobantes y pagos.
     */
    public static function get_compra_con_detalles(int $id_compra_carbon): array
    {
        $compra = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($compra['cabecera'] === null) {
            return ApiResponse::error('Compra de carbón no encontrada');
        }

        return ApiResponse::success($compra);
    }

    /**
     * Registro preliminar de la orden de compra de carbón / cotización.
     * @param array<string, mixed> $payload
     */
    public static function crear_compra(array $payload, int $id_empleado_registro): array
    {
        $idEmpresa = (int) $payload['id_empresa'];
        $idProveedor = (int) $payload['id_proveedor'];
        $idTipoCarbonPrometido = (int) $payload['id_tipo_carbon_prometido'];
        $toneladasPrometidas = (float) $payload['toneladas_prometidas'];
        $aplicaIgv = !empty($payload['aplica_igv']);
        $porcentajeIgv = $aplicaIgv ? (float) ($payload['porcentaje_igv'] ?? 18.0) : 0.0;

        // Auto-seleccionar la tarifa más alta si no viene una explícita
        $idTarifaCarbon = isset($payload['id_tarifa_carbon']) && (int) $payload['id_tarifa_carbon'] > 0
            ? (int) $payload['id_tarifa_carbon']
            : null;

        $precioUnitario = isset($payload['precio_unitario_cotizado']) && (float) $payload['precio_unitario_cotizado'] > 0
            ? (float) $payload['precio_unitario_cotizado']
            : null;

        if ($precioUnitario === null || $idTarifaCarbon === null) {
            $tarifaMax = CompraCarbonData::get_tarifa_max_precio($idTipoCarbonPrometido);
            if ($tarifaMax !== null) {
                $idTarifaCarbon = $idTarifaCarbon ?? (int) $tarifaMax->id;
                $precioUnitario = $precioUnitario ?? (float) $tarifaMax->precio_unitario;
            } else {
                $precioUnitario = $precioUnitario ?? 0.0;
            }
        }

        $totalCotizado = round($toneladasPrometidas * $precioUnitario, 2);
        // Si aplica IGV se calcula el 18% del total cotizado (solo para referencia, no se suma ni resta)
        $montoIgvCotizado = $aplicaIgv ? round($totalCotizado * ($porcentajeIgv / 100), 2) : 0.0;

        $anio = (int) date('Y');
        $correlativoData = CompraCarbonData::generar_correlativo($anio);

        $idCompra = CompraCarbonData::insert_compra([
            'id_empresa' => $idEmpresa,
            'id_proveedor' => $idProveedor,
            'id_empleado_registro' => $id_empleado_registro,
            'id_tipo_carbon_prometido' => $idTipoCarbonPrometido,
            'id_tarifa_carbon' => $idTarifaCarbon,
            'correlativo' => $correlativoData['correlativo'],
            'numero_correlativo' => $correlativoData['numero_correlativo'],
            'aplica_igv' => $aplicaIgv,
            'porcentaje_igv' => $porcentajeIgv,
            'toneladas_prometidas' => $toneladasPrometidas,
            'precio_unitario_cotizado' => $precioUnitario,
            'total_cotizado' => $totalCotizado,
            'monto_igv_cotizado' => $montoIgvCotizado,
        ]);

        $nuevaCompra = CompraCarbonData::get_compra_by_id($idCompra);

        return ApiResponse::success($nuevaCompra, 'Orden de compra preliminar registrada con éxito');
    }

    /**
     * Registro de una o varias cargas entregadas para una compra de carbón.
     * @param array<int, array<string, mixed>> $cargas
     * @param array<string, array<int, UploadedFile>> $archivosPorCarga Clave "evidencias_{index}"
     */
    public static function registrar_cargas(
        int $id_compra_carbon,
        array $cargas,
        int $id_empleado,
        array $archivosPorCarga = []
    ): array {
        $compra = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($compra['cabecera'] === null) {
            return ApiResponse::error('Compra no encontrada');
        }

        if (in_array($compra['cabecera']->estado, [
            EstadoCompraCarbon::Anulado->value,
            EstadoCompraCarbon::Cerrado->value,
            EstadoCompraCarbon::Pagado->value,
        ], true)) {
            return ApiResponse::error("No se pueden añadir cargas a una compra en estado '{$compra['cabecera']->estado}'");
        }

        // Procesar archivos de evidencias por cada carga
        foreach ($cargas as $index => &$carga) {
            $archivos = $archivosPorCarga["evidencias_{$index}"] ?? ($archivosPorCarga['evidencias'] ?? []);
            if (!empty($archivos)) {
                $guardadas = ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_CARGAS, $archivos);
                $carga['evidencias'] = $guardadas;
            }

            // Auto-elegir tarifa según ceniza si no viene especificada
            if (empty($carga['id_tarifa_carbon']) && isset($carga['porcentaje_ceniza'])) {
                $tarifaCeniza = CompraCarbonData::get_tarifa_por_ceniza(
                    (int) $carga['id_tipo_carbon'],
                    (float) $carga['porcentaje_ceniza']
                );
                if ($tarifaCeniza !== null) {
                    $carga['id_tarifa_carbon'] = (int) $tarifaCeniza->id;
                    if (empty($carga['precio_unitario'])) {
                        $carga['precio_unitario'] = (float) $tarifaCeniza->precio_unitario;
                    }
                }
            }
        }
        unset($carga);

        $idsCargas = CompraCarbonData::insert_cargas($id_compra_carbon, $cargas, $id_empleado);

        return ApiResponse::success([
            'ids_cargas' => $idsCargas,
        ], 'Cargas registradas e ingresadas a inventario correctamente');
    }

    /**
     * Cierra la orden de compra de carbón.
     */
    public static function cerrar_compra(int $id_compra_carbon, int $id_empleado): array
    {
        $compra = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($compra['cabecera'] === null) {
            return ApiResponse::error('Compra no encontrada');
        }

        if ($compra['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('No se puede cerrar una compra anulada');
        }

        if ($compra['cabecera']->estado === EstadoCompraCarbon::Cerrado->value) {
            return ApiResponse::error('La compra ya se encuentra cerrada');
        }

        if (count($compra['cargas']) === 0) {
            return ApiResponse::error('No se puede cerrar una orden de compra sin cargas registradas');
        }

        CompraCarbonData::cerrar_compra($id_compra_carbon, $id_empleado);
        return ApiResponse::success(null, 'Compra de carbón cerrada satisfactoriamente');
    }

    /**
     * Anula una orden de compra de carbón.
     */
    public static function anular_compra(int $id_compra_carbon, int $id_empleado): array
    {
        $compra = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($compra['cabecera'] === null) {
            return ApiResponse::error('Compra no encontrada');
        }

        if ($compra['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('La orden ya se encuentra anulada');
        }

        if (!empty($compra['cargas'])) {
            return ApiResponse::error('No se puede anular una orden que ya tiene cargas registradas');
        }

        CompraCarbonData::anular_compra($id_compra_carbon, $id_empleado);
        return ApiResponse::success(null, 'Compra de carbón anulada con éxito');
    }
}
