<?php

namespace App\Modules\TamizajeCarbon\Service;

use App\Modules\TamizajeCarbon\Data\GastoProduccionCarbonData;
use App\Modules\TamizajeCarbon\Data\GrupoGastosData;
use App\Shared\Enums\Carbon\MedioPagoGastoProduccion;
use App\Shared\Enums\Carbon\TipoGastoZarandeo;
use App\Shared\Enums\Carbon\TipoOperacionGrupoGastos;
use App\Shared\Responses\ApiResponse;

/**
 * Gastos de produccion de carbon.
 *
 * Los gastos se registran contra un GRUPO de gastos (que reune varios
 * zarandeos o varios blendings de una misma operacion), no contra una
 * operacion puntual: el usuario hace la operacion una vez al mes pero varias
 * veces, y los gastos corresponden a la operacion completa.
 */
class GastoProduccionCarbonService
{
    /**
     * Lista los gastos de produccion de un grupo.
     */
    public static function get_gastos_grupo(int $id_grupo_gastos): array
    {
        return ApiResponse::success(
            GastoProduccionCarbonData::get_gastos(id_grupo_gastos: $id_grupo_gastos)
        );
    }

    /**
     * Gastos de produccion listos para exportar.
     *
     * Sin grupo: los gastos del tipo pedido (blending o zarandeo) cuya
     * operacion agrupada cae en el rango de fechas. Con grupo: solo los de ese
     * grupo.
     *
     * @param ?string $fecha_inicio Rango por la fecha inicio de la operacion.
     * @param ?string $fecha_fin Rango por la fecha inicio de la operacion.
     * @param ?string $tipo "blending" o "zarandeo". Null = de los dos.
     */
    public static function get_gastos_exportar(
        ?int $id_grupo_gastos = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null,
        ?string $tipo = null,
        ?int $mes = null,
        ?int $anio = null
    ): array
    {
        $gastos = GastoProduccionCarbonData::get_gastos_exportar(
            id_grupo_gastos: $id_grupo_gastos,
            fecha_inicio: $fecha_inicio,
            fecha_fin: $fecha_fin,
            tipo: $tipo,
            mes: $mes,
            anio: $anio
        );

        return ApiResponse::success($gastos);
    }

    /**
     * Confirma el pago de un gasto de producción.
     *
     * La fecha de pago puede ser retroactiva. El medio de pago es obligatorio:
     * un gasto de produccion se paga en efectivo, por transferencia o por
     * Yape, y hace falta para conciliar.
     */
    public static function confirmar_pago_gasto(int $id_gasto, string $fecha_pago, string $medio_pago): array
    {
        if (!GastoProduccionCarbonData::existe_gasto($id_gasto)) {
            return ApiResponse::error('El gasto indicado no existe');
        }

        $medio = MedioPagoGastoProduccion::tryFrom($medio_pago);
        if ($medio === null) {
            return ApiResponse::error('El medio de pago indicado no es válido');
        }

        $actualizado = GastoProduccionCarbonData::actualizar_pago_gasto(
            $id_gasto,
            $fecha_pago,
            $medio->value
        );

        if (!$actualizado) {
            return ApiResponse::error('No se pudo registrar el pago del gasto');
        }

        $gasto = GastoProduccionCarbonData::get_gasto_by_id($id_gasto);

        return ApiResponse::success($gasto, 'Pago del gasto registrado');
    }

    /**
     * Registra un gasto de producción en un grupo.
     *
     * El destino se puede indicar de dos formas: el grupo directamente, o la
     * operacion (zarandeo / blending) a la que pertenece, de la que se
     * deduce el grupo.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_gasto(array $payload, int $id_empleado): array
    {
        $idGrupo = (int) ($payload['id_grupo_gastos'] ?? 0);

        if ($idGrupo > 0) {
            // El tipo de operacion lo define el grupo: no se deduce del
            // payload, que puede no traer ninguna operacion.
            $grupo = GrupoGastosData::get_grupo($idGrupo);
            if ($grupo === null) {
                return ApiResponse::error('El grupo de gastos indicado no existe');
            }
            $tipo = TipoOperacionGrupoGastos::tryFrom((string) $grupo->tipo);
            if ($tipo === null) {
                return ApiResponse::error('El grupo de gastos tiene un tipo de operacion invalido');
            }
        } else {
            // Sin grupo explicito se deduce de la operacion indicada (zarandeo
            // o blending), de la que se obtiene el grupo al que pertenece.
            $tipo = self::tipo_operacion_del_payload($payload);
            $idGrupo = self::resolver_grupo_desde_operacion($payload, $tipo);
            if ($idGrupo <= 0) {
                return ApiResponse::error(
                    'La operacion indicada no pertenece a ningun grupo de gastos: primero agrupe las operaciones y luego registre los gastos contra ese grupo'
                );
            }
        }

        $tipoGasto = TipoGastoZarandeo::tryFrom((string) ($payload['gasto'] ?? ''));
        if ($tipoGasto === null) {
            return ApiResponse::error('El tipo de gasto no es válido');
        }

        $otroGasto = trim((string) ($payload['otro_gasto'] ?? ''));
        if ($tipoGasto === TipoGastoZarandeo::Otro && $otroGasto === '') {
            return ApiResponse::error('Debe describir el concepto del gasto');
        }

        // Pala y Lanza se tarifan por horas trabajadas: el usuario indica
        // cuantas horas se trabajaron, sin marcar hora de inicio y de fin.
        $cantidad = (float) ($payload['cantidad'] ?? 0);
        if ($cantidad <= 0) {
            return ApiResponse::error('La cantidad del gasto debe ser mayor a cero');
        }

        // El proveedor es opcional, pero si viene debe existir: un gasto
        // colgado de un proveedor inexistente no se puede conciliar.
        $idProveedor = (int) ($payload['id_proveedor'] ?? 0);
        if ($idProveedor > 0 && !GastoProduccionCarbonData::existe_proveedor($idProveedor)) {
            return ApiResponse::error('El proveedor indicado no existe');
        }

        $precioUnitario = (float) ($payload['precio_unitario'] ?? 0);
        $total = round($cantidad * $precioUnitario, 2);

        $horaInicio = trim((string) ($payload['hora_inicio'] ?? ''));
        $horaFin = trim((string) ($payload['hora_fin'] ?? ''));

        $idGasto = GastoProduccionCarbonData::insert_gasto([
            'id_empleado_registro' => $id_empleado,
            'id_grupo_gastos' => $idGrupo,
            'id_proveedor' => $idProveedor > 0 ? $idProveedor : null,
            'gasto' => $tipoGasto->value,
            'otro_gasto' => $otroGasto !== '' ? $otroGasto : null,
            'descripcion' => $payload['descripcion'] ?? null,
            'fecha' => (string) $payload['fecha'],
            'numero_reporte' => $payload['numero_reporte'] ?? null,
            'hora_inicio' => $horaInicio !== '' ? $horaInicio : null,
            'hora_fin' => $horaFin !== '' ? $horaFin : null,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'total' => $total,
        ]);

        // Se devuelve el gasto ya persistido con la misma forma que el listado,
        // para que el front lo inserte en su lista sin volver a consultar.
        $nuevoGasto = GastoProduccionCarbonData::get_gasto_by_id($idGasto);

        return ApiResponse::success(
            $nuevoGasto,
            'Gasto de producción registrado'
        );
    }

    /**
     * Deduce el tipo de operacion del payload: si viene un blending es
     * 'blending', si no es 'zarandeo'.
     */
    private static function tipo_operacion_del_payload(array $payload): TipoOperacionGrupoGastos
    {
        $idBlending = (int) ($payload['id_blending_carbon'] ?? 0);

        return $idBlending > 0
            ? TipoOperacionGrupoGastos::Blending
            : TipoOperacionGrupoGastos::Zarandeo;
    }

    /**
     * Deduce el grupo de gastos a partir de la operacion indicada.
     */
    private static function resolver_grupo_desde_operacion(array $payload, TipoOperacionGrupoGastos $tipo): int
    {
        $idOperacion = $tipo === TipoOperacionGrupoGastos::Blending
            ? (int) ($payload['id_blending_carbon'] ?? 0)
            : (int) ($payload['id_zarandeo_carbon'] ?? 0);

        if ($idOperacion <= 0) {
            return 0;
        }

        return GrupoGastosData::get_grupo_de_miembro($idOperacion, $tipo->value) ?? 0;
    }
}
