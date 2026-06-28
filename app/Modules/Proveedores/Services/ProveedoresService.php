<?php

namespace App\Modules\Proveedores\Services;

use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;
use App\Modules\Proveedores\Data\CuentasBancariasData;
use App\Modules\Proveedores\Data\ProveedoresData;
use App\Services\ProveedoresService as ProveedoresServiceGlobal;
use Illuminate\Support\Facades\DB;

class ProveedoresService
{

    public static function get_proveedores(): array
    {
        $data = ProveedoresData::get_proveedores();
        return ApiResponse::success($data, "Proveedores obtenidos correctamente");
    }

    /**
     * @param array $cuentas Listado de cuentas bancarias (opcional)
     * - id_banco (int)
     * - moneda (string)
     * - numero_cuenta (string)
     * - cci (string|null)
     * - es_para_detraccion (int)
     */
    public static function crear_proveedor(
        TipoEntidad $tipoEntidad,
        string $razonSocial,
        bool $paraMantenimiento,
        bool $paraTransporte = false,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $correo = null,
        array $cuentas = []
    ): array {
        return DB::transaction(function () use ($tipoEntidad, $dni, $ruc, $razonSocial, $paraMantenimiento, $paraTransporte, $direccion, $telefono, $correo, $cuentas) {
            $response = ProveedoresServiceGlobal::crear_proveedor(
                tipoEntidad: $tipoEntidad,
                dni: $dni,
                ruc: $ruc,
                razonSocial: $razonSocial,
                paraMantenimiento: $paraMantenimiento,
                paraTransporte: $paraTransporte,
                direccion: $direccion,
                telefono: $telefono,
                correo: $correo
            );

            // Si hubo un error, lo devolvemos
            if ($response['success'] == false) {
                return $response;
            }

            // obtenemos el id generado
            $id = $response['data'];

            foreach ($cuentas as $cta) {
                CuentasBancariasData::crear_cuenta_bancaria(
                    $id,
                    $cta['id_banco'],
                    $cta['moneda'],
                    $cta['numero_cuenta'],
                    $cta['cci'] ?? null,
                    (int) ($cta['es_para_detraccion'] ?? 0)
                );
            }

            $new_proveedor = ProveedoresData::get_proveedor_by_id($id);
            return ApiResponse::success($new_proveedor, "Proveedor registrado correctamente");
        });
    }
}
