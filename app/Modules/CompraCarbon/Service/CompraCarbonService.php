<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonData;
use App\Modules\TipoCarbon\Data\CalidadCarbonData;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CompraCarbonService
{
    private const CARPETA_EVIDENCIAS_CARGAS = 'evidencias-cargas-carbon';

    // Estados en los que NO se permite editar la cabecera. Anulado y Cerrado
    // son terminales; Pagado es estado terminal de liquidacion.
    private const ESTADOS_CABECERA_NO_EDITABLES = [
        'Anulado',
        'Cerrado',
        'Pagado',
    ];

    /**
     * Detraccion SUNAT sobre el carbon: solo aplica si la compra emite IGV.
     * Valor por defecto cuando el registro no lo envia; el porcentaje que
     * llega desde el formulario es el que queda guardado en la compra.
     */
    private const PORCENTAJE_DETRACCION = 10.0;

    /** IGV del flete: el transporte es un servicio facturado aparte, siempre 18%. */
    private const PORCENTAJE_IGV_FLETE = 18.0;

    /** Detraccion SUNAT sobre el flete: se aplica siempre. */
    private const PORCENTAJE_DETRACCION_FLETE = 4.0;

    /**
     * Etiquetas legibles de los campos editables de la cabecera, para que el
     * historial de cambios se entienda.
     */
    private const ETIQUETAS_CAMPO_COMPRA = [
        'id_empresa' => 'Empresa',
        'id_proveedor' => 'Proveedor',
        'id_tipo_carbon_prometido' => 'Tipo de carbón prometido',
        'id_tarifa_carbon' => 'Tarifa de carbón',
        'id_cuenta_proveedor' => 'Cuenta bancaria del proveedor',
        'aplica_igv' => 'Aplica IGV',
        'porcentaje_igv' => 'Porcentaje IGV',
        'porcentaje_detraccion' => 'Porcentaje de detracción',
        'toneladas_prometidas' => 'Toneladas prometidas',
        'precio_unitario_cotizado' => 'Precio unitario cotizado',
    ];

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
     * Edita la cabecera de una compra. Solo aplica si la compra esta en
     * estado Preliminar o En Liquidacion (regla: mientras NO este pagada,
     * cerrada ni anulada).
     *
     * Recalcula `total_cotizado` y `monto_igv_cotizado` si cambian
     * toneladas/precio/aplica_igv/porcentaje_igv.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_compra(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado_editor,
        ?string $nombre_empleado = null,
    ): array {
        $actual = CompraCarbonData::get_compra_by_id($id_compra_carbon);
        if ($actual === null) {
            return ApiResponse::error('Compra de carbon no encontrada', 404);
        }

        if (in_array($actual->estado, self::ESTADOS_CABECERA_NO_EDITABLES, true)) {
            return ApiResponse::error(
                "No se puede editar una compra en estado '{$actual->estado}'",
                422,
            );
        }

        $cambios = [];
        // Campo => admite null. Solo la cuenta del proveedor se puede
        // limpiar (mandar null); el resto con null se ignora.
        $camposEditables = [
            'id_empresa' => false,
            'id_proveedor' => false,
            'id_tipo_carbon_prometido' => false,
            'id_tarifa_carbon' => false,
            'id_cuenta_proveedor' => true,
            'aplica_igv' => false,
            'porcentaje_igv' => false,
            'porcentaje_detraccion' => false,
            'toneladas_prometidas' => false,
            'precio_unitario_cotizado' => false,
        ];
        foreach ($camposEditables as $campo => $admiteNull) {
            if (!array_key_exists($campo, $payload)) {
                continue;
            }
            if ($payload[$campo] === null && !$admiteNull) {
                continue;
            }
            $cambios[$campo] = [
                'antes' => $actual->{$campo} ?? null,
                'despues' => $payload[$campo],
            ];
        }

        if (empty($cambios)) {
            return ApiResponse::success($actual, 'Sin cambios');
        }

        // Recalcular la cascada de totales cuando cambian toneladas / precio /
        // igv / detraccion. Son las MISMAS formulas que usa el registro
        // (crear_compra): subtotal -> IGV -> total -> detraccion -> neto.
        $toneladas = (float) ($cambios['toneladas_prometidas']['despues'] ?? $actual->toneladas_prometidas);
        $precio = (float) ($cambios['precio_unitario_cotizado']['despues'] ?? $actual->precio_unitario_cotizado);
        $aplicaIgv = (bool) ($cambios['aplica_igv']['despues'] ?? ((int) $actual->aplica_igv === 1));
        $porcentajeIgv = $aplicaIgv
            ? (float) ($cambios['porcentaje_igv']['despues'] ?? $actual->porcentaje_igv)
            : 0.0;

        $subtotalCotizado = round($toneladas * $precio, 2);
        $montoIgvCotizado = $aplicaIgv ? round($subtotalCotizado * ($porcentajeIgv / 100), 2) : 0.0;
        $totalCotizado = round($subtotalCotizado + $montoIgvCotizado, 2);

        // La detraccion del carbon solo aplica cuando la compra emite IGV.
        $porcentajeDetraccion = 0.0;
        if ($aplicaIgv) {
            $pct = (float) ($cambios['porcentaje_detraccion']['despues'] ?? $actual->porcentaje_detraccion);
            $porcentajeDetraccion = $pct > 0 ? $pct : self::PORCENTAJE_DETRACCION;
        }
        $montoDetraccionCotizado = $porcentajeDetraccion > 0
            ? round($totalCotizado * ($porcentajeDetraccion / 100), 2)
            : 0.0;
        $netoAPagarCotizado = round($totalCotizado - $montoDetraccionCotizado, 2);

        $update = [];
        foreach ($cambios as $campo => $val) {
            $update[$campo] = $val['despues'];
        }
        $update['aplica_igv'] = $aplicaIgv ? 1 : 0;
        $update['porcentaje_igv'] = $porcentajeIgv;
        $update['porcentaje_detraccion'] = $porcentajeDetraccion;
        $update['subtotal_cotizado'] = $subtotalCotizado;
        $update['monto_igv_cotizado'] = $montoIgvCotizado;
        $update['total_cotizado'] = $totalCotizado;
        $update['monto_detraccion_cotizado'] = $montoDetraccionCotizado;
        $update['neto_a_pagar_cotizado'] = $netoAPagarCotizado;

        // Log de cambios en la misma forma que usa el resto de modulos:
        // lista de {campo_bd, campo, valor_anterior, valor_nuevo}.
        $cambiosLog = [];
        foreach ($cambios as $campo => $val) {
            $cambiosLog[] = [
                'campo_bd' => $campo,
                'campo' => self::ETIQUETAS_CAMPO_COMPRA[$campo] ?? $campo,
                'valor_anterior' => $val['antes'],
                'valor_nuevo' => $val['despues'],
            ];
        }

        CompraCarbonData::update_compra($id_compra_carbon, $update);

        CompraCarbonData::append_log_cambio_compra(
            $id_compra_carbon,
            json_encode([
                'id_empleado' => $id_empleado_editor,
                'nombre_empleado' => $nombre_empleado,
                'motivo' => null,
                'update_at' => now()->toDateTimeString(),
                // Misma forma que usa el resto de modulos: lista de
                // {campo_bd, campo, valor_anterior, valor_nuevo}.
                'cambios' => $cambiosLog,
            ], JSON_UNESCAPED_UNICODE),
        );

        $actualizada = CompraCarbonData::get_compra_by_id($id_compra_carbon);
        return ApiResponse::success($actualizada, 'Compra de carbon actualizada correctamente');
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

        $idCuentaProveedor = isset($payload['id_cuenta_proveedor']) && (int) $payload['id_cuenta_proveedor'] > 0
            ? (int) $payload['id_cuenta_proveedor']
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

        // Cascada cotizada: subtotal -> IGV -> total -> detraccion -> neto.
        $subtotalCotizado = round($toneladasPrometidas * $precioUnitario, 2);
        // El IGV se calcula sobre el subtotal y solo si la compra lo aplica.
        $montoIgvCotizado = $aplicaIgv ? round($subtotalCotizado * ($porcentajeIgv / 100), 2) : 0.0;
        $totalCotizado = round($subtotalCotizado + $montoIgvCotizado, 2);
        // La detraccion del carbon solo aplica cuando la compra emite IGV. El
        // porcentaje llega desde el formulario y queda guardado en la compra.
        $porcentajeDetraccion = 0.0;
        if ($aplicaIgv) {
            $pct = isset($payload['porcentaje_detraccion'])
                ? (float) $payload['porcentaje_detraccion']
                : self::PORCENTAJE_DETRACCION;
            $porcentajeDetraccion = $pct > 0 ? $pct : self::PORCENTAJE_DETRACCION;
        }
        $montoDetraccionCotizado = $porcentajeDetraccion > 0
            ? round($totalCotizado * ($porcentajeDetraccion / 100), 2)
            : 0.0;
        $netoAPagarCotizado = round($totalCotizado - $montoDetraccionCotizado, 2);

        $anio = (int) date('Y');
        $correlativoData = CompraCarbonData::generar_correlativo($anio);

        $idCompra = CompraCarbonData::insert_compra([
            'id_empresa' => $idEmpresa,
            'id_proveedor' => $idProveedor,
            'id_empleado_registro' => $id_empleado_registro,
            'id_tipo_carbon_prometido' => $idTipoCarbonPrometido,
            'id_tarifa_carbon' => $idTarifaCarbon,
            'id_cuenta_proveedor' => $idCuentaProveedor,
            'correlativo' => $correlativoData['correlativo'],
            'numero_correlativo' => $correlativoData['numero_correlativo'],
            'aplica_igv' => $aplicaIgv,
            'porcentaje_igv' => $porcentajeIgv,
            'porcentaje_detraccion' => $porcentajeDetraccion,
            'porcentaje_igv_flete' => self::PORCENTAJE_IGV_FLETE,
            'porcentaje_detraccion_flete' => self::PORCENTAJE_DETRACCION_FLETE,
            'toneladas_prometidas' => $toneladasPrometidas,
            'precio_unitario_cotizado' => $precioUnitario,
            'subtotal_cotizado' => $subtotalCotizado,
            'monto_igv_cotizado' => $montoIgvCotizado,
            'total_cotizado' => $totalCotizado,
            'monto_detraccion_cotizado' => $montoDetraccionCotizado,
            'neto_a_pagar_cotizado' => $netoAPagarCotizado,
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
            // EstadoCompraCarbon::Pagado->value,
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

    /**
     * Edita una carga individual. Solo aplica si la carga:
     *   - no esta Anulada,
     *   - no tiene comprobante de proveedor asignado,
     *   - no tiene pago directo asignado,
     *   - no tiene comprobante de transporte (flete) asignado.
     *
     * Si la carga ya afecto stock (ingreso a almacen de empresa), y los
     * campos stock-affecting cambiaron (id_almacen_empresa_llegada,
     * id_tipo_carbon, cantidad, precio_unitario), se revierte el movimiento
     * viejo y se aplica el nuevo en la misma transaccion. El kardex queda
     * con los dos asientos: el original (Ingreso) y el de reversion (Salida),
     * mas el nuevo Ingreso si corresponde.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_carga(
        int $id_compra_carbon,
        int $id_carga_compra_carbon,
        array $payload,
        int $id_empleado_editor,
        ?string $nombre_empleado = null,
        ?int $id_empleado_registro_reasignado = null,
    ): array {
        try {
            $result = DB::transaction(function () use ($id_compra_carbon, $id_carga_compra_carbon, $payload, $id_empleado_editor, $nombre_empleado, $id_empleado_registro_reasignado) {
                $compra = CompraCarbonData::get_compra_by_id($id_compra_carbon);
                if ($compra === null) {
                    return ApiResponse::error('Compra de carbon no encontrada', 404);
                }
                if (in_array($compra->estado, self::ESTADOS_CABECERA_NO_EDITABLES, true)) {
                    return ApiResponse::error(
                        "No se puede editar cargas de una compra en estado '{$compra->estado}'",
                        422,
                    );
                }

                $carga = CompraCarbonData::get_carga_by_id($id_carga_compra_carbon);
                if ($carga === null) {
                    return ApiResponse::error('Carga no encontrada', 404);
                }
                if ((int) $carga->id_compra_carbon !== $id_compra_carbon) {
                    return ApiResponse::error('La carga no pertenece a la compra indicada', 422);
                }

                // Reglas de inmutabilidad: si ya esta asociada a comprobante /
                // pago / comprobante de flete, no se puede editar.
                if (!empty($carga->id_comprobante_compra_carbon)
                    || !empty($carga->id_pago_compra_carbon)
                    || !empty($carga->id_comprobante_transporte_carbon)
                ) {
                    return ApiResponse::error(
                        'La carga ya esta asociada a un comprobante o pago; no se puede editar',
                        422,
                    );
                }
                if ($carga->estado === 'Anulado') {
                    return ApiResponse::error('La carga esta anulada', 422);
                }

                // Valores finales (post-cambio)
                $nuevoAlmacen = array_key_exists('id_almacen_empresa_llegada', $payload)
                    ? ($payload['id_almacen_empresa_llegada'] !== null ? (int) $payload['id_almacen_empresa_llegada'] : null)
                    : ((int) $carga->id_almacen_empresa_llegada ?: null);
                $nuevoTipo = array_key_exists('id_tipo_carbon', $payload)
                    ? (int) $payload['id_tipo_carbon']
                    : (int) $carga->id_tipo_carbon;
                $nuevaCantidad = array_key_exists('cantidad', $payload)
                    ? (float) $payload['cantidad']
                    : (float) $carga->cantidad;
                $nuevoPrecio = array_key_exists('precio_unitario', $payload)
                    ? (float) $payload['precio_unitario']
                    : (float) $carga->precio_unitario;
                $nuevaFechaHora = array_key_exists('fecha_hora_ingreso', $payload)
                    ? (string) $payload['fecha_hora_ingreso']
                    : (string) $carga->fecha_hora_ingreso;
                $nuevoPorcentajeCeniza = array_key_exists('porcentaje_ceniza', $payload)
                    ? (float) $payload['porcentaje_ceniza']
                    : (float) $carga->porcentaje_ceniza;

                $viejoAlmacen = $carga->id_almacen_empresa_llegada ? (int) $carga->id_almacen_empresa_llegada : null;
                $viejoTipo = (int) $carga->id_tipo_carbon;
                $viejaCantidad = (float) $carga->cantidad;
                $viejoPrecio = (float) $carga->precio_unitario;
                $viejoPagarFlete = (bool) $carga->pagar_flete;
                $viejoCostoFlete = (float) $carga->costo_flete_por_tonelada;

                // El stock esta segmentado por (almacen, tipo de carbon, calidad).
                // La calidad VIEJA es la con la que la carga ingreso al stock
                // (queda guardada en la carga); la nueva se deriva del % de
                // ceniza final.
                $viejaCalidad = $carga->calidad !== null ? (string) $carga->calidad : null;
                $calidadResuelta = CalidadCarbonData::resolver_por_ceniza($nuevoPorcentajeCeniza);
                $nuevaCalidad = $calidadResuelta !== null ? (string) $calidadResuelta->nombre : null;

                $stockAplicadoOriginal = $viejoAlmacen !== null && $viejaCantidad > 0;

                // ── COSTOS DEL CARBON ──────────────────────────────────────
                // Mismas formulas que usa el registro (insert_cargas): el
                // flete lo asume la empresa, asi que sale del precio unitario
                // que se le reconoce al proveedor por el carbon.
                $porcentajeIgv = (float) $compra->porcentaje_igv;
                $porcentajeDetraccion = (float) $compra->porcentaje_detraccion;
                $porcentajeIgvFlete = (float) $compra->porcentaje_igv_flete;
                $porcentajeDetraccionFlete = (float) $compra->porcentaje_detraccion_flete;

                $pagarFleteNuevo = array_key_exists('pagar_flete', $payload)
                    ? (bool) $payload['pagar_flete']
                    : $viejoPagarFlete;
                $costoFleteNuevo = array_key_exists('costo_flete_por_tonelada', $payload)
                    ? (float) $payload['costo_flete_por_tonelada']
                    : $viejoCostoFlete;
                $costoFlete = $pagarFleteNuevo ? $costoFleteNuevo : 0.0;

                $precioMateriaPrima = max(0.0, round($nuevoPrecio - $costoFlete, 6));
                $subtotal = round($precioMateriaPrima * $nuevaCantidad, 2);
                $montoIgv = $porcentajeIgv > 0 ? round($subtotal * ($porcentajeIgv / 100), 2) : 0.0;
                $total = round($subtotal + $montoIgv, 2);
                $montoDetraccion = $porcentajeDetraccion > 0 ? round($total * ($porcentajeDetraccion / 100), 2) : 0.0;
                $netoAPagar = round($total - $montoDetraccion, 2);

                // ── COSTOS DE TRANSPORTE ────────────────────────────────────
                $fleteSubtotal = $pagarFleteNuevo ? round($nuevaCantidad * $costoFlete, 2) : 0.0;
                $fleteIgv = $porcentajeIgvFlete > 0 ? round($fleteSubtotal * ($porcentajeIgvFlete / 100), 2) : 0.0;
                $fleteTotal = round($fleteSubtotal + $fleteIgv, 2);
                $fleteMontoDetraccion = $porcentajeDetraccionFlete > 0 ? round($fleteTotal * ($porcentajeDetraccionFlete / 100), 2) : 0.0;
                $fleteNetoAPagar = round($fleteTotal - $fleteMontoDetraccion, 2);

                // Costo del material ingresado a almacen: carbon + flete, sin IGV.
                $costoTotal = round($subtotal + $fleteSubtotal, 2);

                // 1) Revertir movimiento de stock previo, si aplica y si algo cambia.
                $stockAplicaNuevo = $nuevoAlmacen !== null && $nuevaCantidad > 0;
                $cambioStock = $nuevoAlmacen !== $viejoAlmacen
                    || $nuevoTipo !== $viejoTipo
                    || abs($nuevaCantidad - $viejaCantidad) > 0.0001
                    || $nuevaCalidad !== $viejaCalidad
                    || !$stockAplicaNuevo;

                if ($stockAplicadoOriginal && $cambioStock) {
                    // Costo total viejo: carbon (sin flete) + flete, sin IGV.
                    $costoFleteViejo = $viejoPagarFlete ? $viejoCostoFlete : 0.0;
                    $subtotalViejo = round(max(0.0, round($viejoPrecio - $costoFleteViejo, 6)) * $viejaCantidad, 2);
                    $fleteSubtotalViejo = $viejoPagarFlete ? round($viejaCantidad * $costoFleteViejo, 2) : 0.0;
                    $costoTotalViejo = round($subtotalViejo + $fleteSubtotalViejo, 2);
                    CompraCarbonData::revertir_stock_ingreso_carga(
                        idAlmacen: $viejoAlmacen,
                        idTipoCarbon: $viejoTipo,
                        calidad: $viejaCalidad,
                        idCarga: $id_carga_compra_carbon,
                        cantidad: $viejaCantidad,
                        costoTotal: $costoTotalViejo,
                        fechaHoraMovimiento: $nuevaFechaHora,
                    );
                }

                // 2) Aplicar nuevo movimiento de stock, si corresponde.
                if ($stockAplicaNuevo && ($cambioStock || !$stockAplicadoOriginal)) {
                    CompraCarbonData::afectar_stock_ingreso_carga(
                        idAlmacen: $nuevoAlmacen,
                        idTipoCarbon: $nuevoTipo,
                        idCarga: $id_carga_compra_carbon,
                        cantidad: $nuevaCantidad,
                        calidad: $nuevaCalidad,
                        costoTotal: $costoTotal,
                        fechaHoraIngreso: $nuevaFechaHora,
                    );
                }

                // 3) Aplicar update de los campos editables + subtotales recalculados.
                $update = [];
                $campos = [
                    'id_tipo_carbon',
                    'id_lugar_extraccion',
                    'id_almacen_proveedor_recojo',
                    'id_almacen_empresa_llegada',
                    'id_almacen_cliente_llegada',
                    'id_tarifa_carbon',
                    'id_transportista',
                    'tipo_despacho',
                    'placa',
                    'fecha_hora_ingreso',
                    'guia_remitente',
                    'guia_transportista',
                    'pagar_flete',
                    'codigo_ticket_balanza',
                    'cantidad',
                    'porcentaje_ceniza',
                    'porcentaje_humedad',
                    'precio_unitario',
                    'costo_flete_por_tonelada',
                    'evidencias',
                ];
                foreach ($campos as $c) {
                    if (array_key_exists($c, $payload)) {
                        $update[$c] = $payload[$c];
                    }
                }
                // Montos derivados: se recalculan con las mismas formulas del
                // registro para que la carga no quede con montos viejos.
                $update['calidad'] = $nuevaCalidad;
                $update['precio_materia_prima'] = $precioMateriaPrima;
                $update['subtotal'] = $subtotal;
                $update['monto_igv'] = $montoIgv;
                $update['total'] = $total;
                $update['monto_detraccion'] = $montoDetraccion;
                $update['neto_a_pagar'] = $netoAPagar;
                $update['flete_subtotal'] = $fleteSubtotal;
                $update['flete_igv'] = $fleteIgv;
                $update['flete_total'] = $fleteTotal;
                $update['flete_monto_detraccion'] = $fleteMontoDetraccion;
                $update['flete_neto_a_pagar'] = $fleteNetoAPagar;

                CompraCarbonData::update_carga($id_carga_compra_carbon, $update);

                CompraCarbonData::append_log_cambio_carga(
                    $id_carga_compra_carbon,
                    json_encode([
                        'id_empleado' => $id_empleado_editor,
                        'nombre_empleado' => $nombre_empleado,
                        'motivo' => null,
                        'update_at' => now()->toDateTimeString(),
                        // Misma forma que usa el resto de modulos: lista de
                        // {campo_bd, campo, valor_anterior, valor_nuevo}.
                        'cambios' => [
                            [
                                'campo_bd' => 'id_almacen_empresa_llegada',
                                'campo' => 'Almacén de llegada',
                                'valor_anterior' => $viejoAlmacen,
                                'valor_nuevo' => $nuevoAlmacen,
                            ],
                            [
                                'campo_bd' => 'id_tipo_carbon',
                                'campo' => 'Tipo de carbón',
                                'valor_anterior' => $viejoTipo,
                                'valor_nuevo' => $nuevoTipo,
                            ],
                            [
                                'campo_bd' => 'cantidad',
                                'campo' => 'Toneladas',
                                'valor_anterior' => $viejaCantidad,
                                'valor_nuevo' => $nuevaCantidad,
                            ],
                            [
                                'campo_bd' => 'precio_unitario',
                                'campo' => 'Precio unitario',
                                'valor_anterior' => $viejoPrecio,
                                'valor_nuevo' => $nuevoPrecio,
                            ],
                            [
                                'campo_bd' => 'porcentaje_ceniza',
                                'campo' => '% ceniza',
                                'valor_anterior' => (float) $carga->porcentaje_ceniza,
                                'valor_nuevo' => $nuevoPorcentajeCeniza,
                            ],
                            [
                                'campo_bd' => 'calidad',
                                'campo' => 'Calidad',
                                'valor_anterior' => $viejaCalidad,
                                'valor_nuevo' => $nuevaCalidad,
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                );

                $cargaActualizada = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
                return ApiResponse::success(
                    $cargaActualizada,
                    'Carga actualizada correctamente',
                );
            });
            return $result;
        } catch (\Throwable $e) {
            return ApiResponse::error('Error al actualizar la carga: ' . $e->getMessage(), 500);
        }
    }
}
