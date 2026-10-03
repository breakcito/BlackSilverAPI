<?php

namespace App\Data;

use App\Models\CuentaBancariaTransportista;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas bancarias de los transportistas del modulo de Compra de Carbon.
 *
 * La tabla existia como modelo huerfano, sin capa de datos ni endpoints. Se
 * expone desde `aux` porque es un catalogo recurrente (mismo criterio que las
 * cuentas de empresa, proveedor y cliente).
 */
class TransportistasCuentasData
{
    public static function get_cuentas(
        int|array|null $id_transportista = null,
        int|array|null $id_cuenta_bancaria = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $query = DB::table('cuenta_bancaria_transportista as cn')
            ->select([
                'cn.id as id_cuenta_bancaria',
                'cn.id_transportista',
                'cn.id_banco',
                'bc.nombre as banco',
                'bc.abreviatura as banco_abv',
                'bc.es_nacional',
                'cn.moneda',
                'cn.numero_cuenta',
                'cn.cci',
                'cn.es_para_detraccion',
                'cn.estado',
            ])
            ->join('banco as bc', 'bc.id', '=', 'cn.id_banco')

            ->when($id_transportista !== null, function ($q) use ($id_transportista) {
                is_array($id_transportista)
                    ? $q->whereIn('cn.id_transportista', $id_transportista)
                    : $q->where('cn.id_transportista', $id_transportista);
            })

            ->when($id_cuenta_bancaria !== null, function ($q) use ($id_cuenta_bancaria) {
                is_array($id_cuenta_bancaria)
                    ? $q->whereIn('cn.id', $id_cuenta_bancaria)
                    : $q->where('cn.id', $id_cuenta_bancaria);
            })

            ->when($estado !== null, fn($q) => $q->where('cn.estado', $estado->value))

            ->orderByDesc('cn.es_para_detraccion')
            ->orderBy('cn.numero_cuenta');

        return is_int($id_cuenta_bancaria)
            ? $query->first()
            : $query->get();
    }

    public static function crear_cuenta(
        int $id_transportista,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): int {
        return CuentaBancariaTransportista::insertGetId([
            'id_transportista' => $id_transportista,
            'id_banco' => $id_banco,
            'moneda' => $moneda->value,
            'numero_cuenta' => $numero_cuenta,
            'cci' => $cci,
            'es_para_detraccion' => $es_para_detraccion ? 1 : 0,
            'estado' => EstadoBase::Activo->value,
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
        return (bool) CuentaBancariaTransportista::where('id', $id_cuenta_bancaria)
            ->update([
                'id_banco' => $id_banco,
                'moneda' => $moneda->value,
                'numero_cuenta' => $numero_cuenta,
                'cci' => $cci,
                'es_para_detraccion' => $es_para_detraccion ? 1 : 0,
            ]);
    }

    public static function ya_existe(
        int $id_transportista,
        int $id_banco,
        string $numero_cuenta,
        ?int $excluir_id = null
    ): bool {
        return CuentaBancariaTransportista::where('id_transportista', $id_transportista)
            ->where('id_banco', $id_banco)
            ->where('numero_cuenta', $numero_cuenta)
            ->when($excluir_id !== null, fn($q) => $q->where('id', '!=', $excluir_id))
            ->exists();
    }

    public static function get_transportista_id_by_cuenta(int $id_cuenta_bancaria): ?int
    {
        $row = DB::table('cuenta_bancaria_transportista')
            ->where('id', $id_cuenta_bancaria)
            ->first(['id_transportista']);

        return $row ? (int) $row->id_transportista : null;
    }
}
