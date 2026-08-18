<?php

namespace App\Modules\Proveedores\Services;

use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;
use App\Modules\Proveedores\Data\CuentasBancariasData;
use App\Modules\Proveedores\Data\ProveedoresData;
use App\Modules\ProveedorCarbon\Data\ProveedorCarbonData;
use App\Services\ProveedoresService as ProveedoresServiceGlobal;
use Illuminate\Support\Facades\DB;

class ProveedoresService
{

    public static function get_proveedores(?bool $paraCarbon = null): array
    {
        $data = ProveedoresData::get_proveedores(paraCarbon: $paraCarbon);

        if (!is_array($data) || empty($data)) {
            return ApiResponse::success($data, "Proveedores obtenidos correctamente");
        }

        $ids = array_map(fn($p) => (int) $p->id_proveedor, $data);
        $cuentas = collect(CuentasBancariasData::get_cuentas_bancarias(ids_proveedor: $ids));
        $tiposCarbon = collect(ProveedorCarbonData::get_tipos_por_proveedores($ids));

        foreach ($data as $proveedor) {
            $proveedor->cuentas_bancarias = $cuentas->where('id_proveedor', $proveedor->id_proveedor)->values();
            $proveedor->tipos_carbon = $tiposCarbon->where('id_proveedor', $proveedor->id_proveedor)->values();
        }

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
        bool $paraCarbon = false,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        array $cuentas = []
    ): array {
        return DB::transaction(function () use ($tipoEntidad, $dni, $ruc, $razonSocial, $paraMantenimiento, $paraTransporte, $direccion, $telefono, $correo, $paraCarbon, $id_departamento, $id_provincia, $id_distrito, $cuentas) {
            $response = ProveedoresServiceGlobal::crear_proveedor(
                tipoEntidad: $tipoEntidad,
                dni: $dni,
                ruc: $ruc,
                razonSocial: $razonSocial,
                paraMantenimiento: $paraMantenimiento,
                paraTransporte: $paraTransporte,
                direccion: $direccion,
                telefono: $telefono,
                correo: $correo,
                paraCarbon: $paraCarbon,
                id_departamento: $id_departamento,
                id_provincia: $id_provincia,
                id_distrito: $id_distrito
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