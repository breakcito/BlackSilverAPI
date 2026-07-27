<?php

namespace App\Data;

use App\Models\CuentaBancariaEmpresa;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use Illuminate\Support\Facades\DB;

class CuentasEmpresaData
{
    public static function get_cuentas(
        int|array|null $id_empresa = null,
        int|array|null $id_cuenta_bancaria = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $query = DB::table('cuenta_bancaria_empresa as cn')
            ->select([
                'cn.id as id_cuenta_bancaria',
                'cn.id_empresa',
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

            ->when($id_empresa !== null, function ($q) use ($id_empresa) {
                is_array($id_empresa)
                    ? $q->whereIn('cn.id_empresa', $id_empresa)
                    : $q->where('cn.id_empresa', $id_empresa);
            })

            ->when($id_cuenta_bancaria !== null, function ($q) use ($id_cuenta_bancaria) {
                is_array($id_cuenta_bancaria)
                    ? $q->whereIn('cn.id', $id_cuenta_bancaria)
                    : $q->where('cn.id', $id_cuenta_bancaria);
            })

            ->when($estado !== null, fn($q) => $q->where('cn.estado', $estado->value))

            ->orderByDesc('cn.es_para_detraccion')
            ->orderBy('cn.moneda')
            ->orderBy('cn.numero_cuenta');

        return is_int($id_cuenta_bancaria)
            ? $query->first()
            : $query->get();
    }

    public static function get_by_id(int $id_cuenta_bancaria): ?object
    {
        return DB::table('cuenta_bancaria_empresa')
            ->where('id', $id_cuenta_bancaria)
            ->first();
    }

    public static function crear_cuenta(
        int $id_empresa,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): int {
        return CuentaBancariaEmpresa::insertGetId([
            'id_empresa' => $id_empresa,
            'id_banco' => $id_banco,
            'moneda' => $moneda->value,
            'numero_cuenta' => $numero_cuenta,
            'cci' => $cci,
            'es_para_detraccion' => $es_para_detraccion ? 1 : 0,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function actualizar_cuenta(
        int $id_cuenta_bancaria,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): bool {
        return (bool) DB::table('cuenta_bancaria_empresa')
            ->where('id', $id_cuenta_bancaria)
            ->update([
                'id_banco' => $id_banco,
                'moneda' => $moneda->value,
                'numero_cuenta' => $numero_cuenta,
                'cci' => $cci,
                'es_para_detraccion' => $es_para_detraccion ? 1 : 0,
            ]);
    }

    public static function cambiar_estado(
        int $id_cuenta_bancaria,
        EstadoBase $estado
    ): bool {
        return (bool) DB::table('cuenta_bancaria_empresa')
            ->where('id', $id_cuenta_bancaria)
            ->update(['estado' => $estado->value]);
    }

    public static function banco_es_nacional(int $id_banco): bool
    {
        return (bool) DB::table('banco')
            ->where('id', $id_banco)
            ->where('es_nacional', 1)
            ->exists();
    }

    public static function ya_existe(
        int $id_empresa,
        int $id_banco,
        ?string $numero_cuenta,
        ?string $cci,
        ?int $excluir_id = null
    ): bool {
        if (blank($numero_cuenta) && blank($cci)) {
            return false;
        }

        return CuentaBancariaEmpresa::where('id_empresa', $id_empresa)
            ->where('id_banco', $id_banco)
            ->when($excluir_id !== null, fn($q) => $q->where('id', '!=', $excluir_id))
            ->where(function ($query) use ($numero_cuenta, $cci) {
                if (filled($numero_cuenta)) {
                    $query->orWhere('numero_cuenta', $numero_cuenta);
                }
                if (filled($cci)) {
                    $query->orWhere('cci', $cci);
                }
            })
            ->exists();
    }
}
