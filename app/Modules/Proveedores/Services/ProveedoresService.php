<?php

namespace App\Modules\Proveedores\Services;

use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;
use App\Modules\Proveedores\Data\CuentasBancariasData;
use App\Modules\Proveedores\Data\ProveedoresData;
use App\Modules\ProveedorCarbon\Data\ProveedorCarbonData;
use App\Modules\LugarExtraccionCarbon\Data\LugarExtraccionCarbonData;
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
        $lugaresExtraccion = collect(LugarExtraccionCarbonData::get_por_proveedores($ids));

        foreach ($data as $proveedor) {
            $proveedor->cuentas_bancarias = $cuentas->where('id_proveedor', $proveedor->id_proveedor)->values();
            $proveedor->tipos_carbon = $tiposCarbon->where('id_proveedor', $proveedor->id_proveedor)->values();
            $proveedor->lugares_extraccion = $lugaresExtraccion->where('id_proveedor', $proveedor->id_proveedor)->values();
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
        array $cuentas = []
    ): array {
        return DB::transaction(function () use ($tipoEntidad, $dni, $ruc, $razonSocial, $paraMantenimiento, $paraTransporte, $direccion, $telefono, $correo, $paraCarbon, $cuentas) {
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

    /**
     * Actualizar un proveedor (logística o carbón).
     *
     * `para_carbon` no se recibe: define en qué pestaña vive el proveedor y se
     * preserva. En el formulario de carbón tampoco se muestran
     * `para_mantenimiento` / `para_transporte`, así que el frontend reenvía los
     * valores actuales para no borrarlos.
     */
    public static function actualizar_proveedor(
        int $id_proveedor,
        TipoEntidad $tipoEntidad,
        string $razonSocial,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $correo = null,
        bool $paraMantenimiento = false,
        bool $paraTransporte = false,
        ?int $idEmpleado = null,
        ?string $nombreEmpleado = null
    ): array {
        // 1. Validar que el proveedor exista
        $existe = ProveedoresData::get_proveedores(id_proveedor: $id_proveedor);
        if (! $existe) {
            return ApiResponse::error('El proveedor que intenta editar no existe.');
        }

        // 2. Validar que no choque con OTRO proveedor por dni / ruc / razón social
        $duplicado = ProveedoresData::existe_duplicado(
            excluir_id: $id_proveedor,
            dni: $dni,
            ruc: $ruc,
            razon_social: $razonSocial,
        );
        if ($duplicado) {
            return ApiResponse::error('Ya existe otro proveedor con el mismo RUC, DNI o razón social.');
        }

        // 3. Persistir cambios (+ diff en cambios_log)
        ProveedoresData::actualizar_proveedor(
            id_proveedor: $id_proveedor,
            tipo_entidad: $tipoEntidad->value,
            razon_social: $razonSocial,
            dni: $dni,
            ruc: $ruc,
            direccion: $direccion,
            telefono: $telefono,
            correo: $correo,
            para_mantenimiento: $paraMantenimiento,
            para_transporte: $paraTransporte,
            id_empleado: $idEmpleado,
            nombre_empleado: $nombreEmpleado,
        );

        // 4. Devolver el proveedor refrescado con la MISMA forma que el listado
        //    (incluye contadores y las colecciones anidadas), para que el
        //    frontend pueda reemplazar la fila sin perder datos.
        return ApiResponse::success(
            self::get_proveedor_con_relaciones($id_proveedor),
            'Proveedor actualizado correctamente',
        );
    }

    /**
     * Desactivar un proveedor (soft delete).
     *
     * No se bloquea por referencias históricas: si al proveedor ya se le
     * compró (compra_carbon, orden_compra), igual debe poder darse de baja.
     */
    public static function eliminar_proveedor(int $id_proveedor): array
    {
        $existe = ProveedoresData::get_proveedores(id_proveedor: $id_proveedor);
        if (! $existe) {
            return ApiResponse::error('El proveedor que intenta eliminar no existe.');
        }

        ProveedoresData::eliminar_proveedor(id_proveedor: $id_proveedor);

        // Devolvemos el proveedor ya Inactivo para que el frontend lo retire
        // de la lista con el mismo shape que entrega listar.
        return ApiResponse::success(
            self::get_proveedor_con_relaciones($id_proveedor),
            'Proveedor eliminado correctamente',
        );
    }

    /**
     * Hidrata un proveedor puntual con las mismas colecciones anidadas que
     * `get_proveedores()` agrega al listado (cuentas, tipos de carbón y
     * lugares de extracción). Sin esto, la fila devuelta por el PUT/DELETE
     * perdería esos arrays y la UI se quedaría sin ellos hasta recargar.
     */
    private static function get_proveedor_con_relaciones(int $id_proveedor)
    {
        $proveedor = ProveedoresData::get_proveedor_by_id($id_proveedor);
        if (! $proveedor) {
            return $proveedor;
        }

        $ids = [$id_proveedor];
        $proveedor->cuentas_bancarias = collect(
            CuentasBancariasData::get_cuentas_bancarias(ids_proveedor: $ids)
        )->where('id_proveedor', $proveedor->id_proveedor)->values();
        $proveedor->tipos_carbon = collect(
            ProveedorCarbonData::get_tipos_por_proveedores($ids)
        )->where('id_proveedor', $proveedor->id_proveedor)->values();
        $proveedor->lugares_extraccion = collect(
            LugarExtraccionCarbonData::get_por_proveedores($ids)
        )->where('id_proveedor', $proveedor->id_proveedor)->values();

        return $proveedor;
    }
}