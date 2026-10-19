<?php

namespace App\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use Illuminate\Support\Facades\DB;

class CuentasProveedorData
{
    public static function get_cuentas(
        int|array|null $id_proveedor = null,
        int|array|null $id_cuenta_bancaria = null,
        ?Moneda $moneda = null,
        ?bool $es_para_detraccion = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $query = DB::table('cuenta_bancaria_proveedor as cn')
            ->select([
                'cn.id_proveedor',
                'cn.id as id_cuenta_bancaria',
                'cn.id_banco',
                'bc.nombre as banco',
                'bc.abreviatura as banco_abv',
                'cn.moneda',
                'cn.numero_cuenta',
                'cn.cci',
                'cn.es_para_detraccion',
                'cn.estado',
            ])
            ->join('banco as bc', 'bc.id', '=', 'cn.id_banco')

            ->when($id_proveedor !== null, function ($q) use ($id_proveedor) {
                is_array($id_proveedor)
                    ? $q->whereIn('cn.id_proveedor', $id_proveedor)
                    : $q->where('cn.id_proveedor', $id_proveedor);
            })

            ->when($id_cuenta_bancaria !== null, function ($q) use ($id_cuenta_bancaria) {
                is_array($id_cuenta_bancaria)
                    ? $q->whereIn('cn.id', $id_cuenta_bancaria)
                    : $q->where('cn.id', $id_cuenta_bancaria);
            })

            ->when($moneda !== null, fn($q) => $q->where('cn.moneda', $moneda->value))

            ->when($es_para_detraccion !== null, fn($q) => $q->where('cn.es_para_detraccion', $es_para_detraccion ? 1 : 0))

            ->when($estado !== null, fn($q) => $q->where('cn.estado', $estado->value))

            ->orderByDesc('cn.es_para_detraccion')
            ->orderBy('cn.moneda')
            ->orderBy('cn.numero_cuenta');

        return is_int($id_cuenta_bancaria)
            ? $query->first()
            : $query->get();
    }
}