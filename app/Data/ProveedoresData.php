<?php

namespace App\Data;

use App\Models\Proveedor;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoEntidad;
use Illuminate\Support\Facades\DB;

class ProveedoresData
{

    /**
     * Listado proveedores
     */
    public static function get_proveedores(
        ?int $id_proveedor = null,
        ?EstadoBase $estado = null,
        ?TipoEntidad $tipoEntidad = null,
        ?bool $paraMantenimiento = null,
        ?bool $paraTransporte = null,
        ?bool $paraCarbon = null
    ) {
        $sql = '
        SELECT
            p.id AS id_proveedor,
            p.razon_social,
            p.direccion,
            p.ruc,
            p.dni,
            p.tipo_entidad,
            p.para_mantenimiento,
            p.para_transporte,
            p.para_carbon,
            p.id_departamento,
            p.id_provincia,
            p.id_distrito,
            d.nombre AS departamento_nombre,
            pv.nombre AS provincia_nombre,
            di.nombre AS distrito_nombre,
            p.estado,
            (
                SELECT COUNT(*)
                FROM cuenta_bancaria_proveedor cn
                WHERE cn.id_proveedor = p.id AND cn.estado = "Activo"
            ) AS cantidad_cuentas_bancarias
        FROM proveedor p
        LEFT JOIN departamento d ON d.id = p.id_departamento
        LEFT JOIN provincia pv ON pv.id = p.id_provincia
        LEFT JOIN distrito di ON di.id = p.id_distrito
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_proveedor !== null) {
            $sql .= 'AND p.id = :id_proveedor';
            $params['id_proveedor'] = $id_proveedor;
            return DB::selectOne($sql, $params);
        }

        if($paraMantenimiento !== null) {
            $sql .= 'AND p.para_mantenimiento = :paraMantenimiento';
            $params['paraMantenimiento'] = $paraMantenimiento ? 1 : 0;
        }

        if($paraTransporte !== null) {
            $sql .= 'AND p.para_transporte = :paraTransporte';
            $params['paraTransporte'] = $paraTransporte ? 1 : 0;
        }

        if ($paraCarbon !== null) {
            $sql .= 'AND p.para_carbon = :paraCarbon';
            $params['paraCarbon'] = $paraCarbon ? 1 : 0;
        }

        if ($estado !== null) {
            $sql .= 'AND p.estado = :estado';
            $params['estado'] = $estado->value;
        }

        if ($tipoEntidad !== null) {
            $sql .= 'AND p.tipo_entidad = :tipoEntidad';
            $params['tipoEntidad'] = $tipoEntidad->value;
        }

        $sql .= ' ORDER BY p.razon_social ASC';

        return DB::select($sql, $params);
    }


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
        ?int $id_distrito = null
    ): int {
        return Proveedor::insertGetId([
            'tipo_entidad' => $tipoEntidad->value,
            'dni' => $dni,
            'ruc' => $ruc,
            'razon_social' => $razonSocial,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
            'para_mantenimiento' => $paraMantenimiento,
            'para_transporte' => $paraTransporte,
            'para_carbon' => $paraCarbon,
            'id_departamento' => $id_departamento,
            'id_provincia' => $id_provincia,
            'id_distrito' => $id_distrito,
            'estado' => 'Activo'
        ]);
    }

    /**
     * Verificar si ya existe por razon social o dni o ruc
     */
    public static function ya_existe(
        ?string $dni = null,
        ?string $ruc = null,
        ?string $razonSocial = null
    ): bool {
        if ($dni === null && $ruc === null && $razonSocial === null) {
            return false;
        }

        return Proveedor::query()
            ->where(function ($q) use ($dni, $ruc, $razonSocial) {
                $q->when($dni !== null, fn($q) => $q->orWhere('dni', $dni))
                    ->when($ruc !== null, fn($q) => $q->orWhere('ruc', $ruc))
                    ->when($razonSocial !== null, fn($q) => $q->orWhere('razon_social', $razonSocial));
            })
            ->exists();
    }
}
