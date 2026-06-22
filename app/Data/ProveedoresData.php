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
        ?bool $paraMantenimiento = null
    ) {
        $sql = '
        SELECT 
            p.id AS id_proveedor,
            p.razon_social,
            p.direccion,
            p.ruc,
            p.dni,
            p.tipo_entidad,
            p.para_mantenimiento
        FROM proveedor p
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
        ?string $dni = null,
        ?string $ruc = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $correo = null
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
