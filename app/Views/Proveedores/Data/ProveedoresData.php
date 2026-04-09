<?php

namespace App\Views\Proveedores\Data;

use Illuminate\Support\Facades\DB;

class ProveedoresData
{
    public function get_proveedores(): array
    {
        return DB::select('
            SELECT
                pr.id AS id_proveedor,
                pr.tipo_entidad,
                pr.dni,
                pr.ruc,
                pr.razon_social,
                pr.direccion,
                pr.telefono,
                pr.correo,
                pr.estado
            FROM
                proveedor pr
            ORDER BY pr.id DESC;
        ');
    }

    public function crear_proveedor(string $tipoEntidad, ?string $dni, ?string $ruc, string $razonSocial, ?string $direccion, ?string $telefono, ?string $correo): int
    {
        return DB::table('proveedor')->insertGetId([
            'tipo_entidad' => $tipoEntidad,
            'dni' => $dni,
            'ruc' => $ruc,
            'razon_social' => $razonSocial,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
            'estado' => 'Activo'
        ]);
    }
}
