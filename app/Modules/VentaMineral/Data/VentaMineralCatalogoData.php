<?php

namespace App\Modules\VentaMineral\Data;

use Illuminate\Support\Facades\DB;

/**
 * Data de los catalogos del modulo de venta de mineral: molinos,
 * transportistas de mineral y laboratorios.
 *
 * No viven en la capa global de Aux porque son catalogos exclusivos de este
 * proceso: ningun otro modulo los usa (regla del README API: no duplicar
 * endpoints genericos, pero tampoco inventar un catalogo global para algo
 * que solo existe aqui).
 */
class VentaMineralCatalogoData
{
    /**
     * Molinos (destino de la molienda).
     */
    public static function get_molinos(): array
    {
        return DB::table('molino')->orderBy('razon_social', 'asc')->get()->all();
    }

    /**
     * Un molino.
     */
    public static function get_molino(int $id_molino): ?object
    {
        return DB::table('molino')->where('id', $id_molino)->first();
    }

    /**
     * Indica si ya existe un molino con esos datos.
     */
    public static function existe_molino(string $razon_social, ?string $ruc): bool
    {
        $query = DB::table('molino')->where('razon_social', $razon_social);

        if ($ruc !== null && $ruc !== '') {
            $query->where('ruc', $ruc);
        }

        return $query->exists();
    }

    /**
     * Registra un molino.
     */
    public static function registrar_molino(string $razon_social, ?string $ruc): int
    {
        return DB::table('molino')->insertGetId([
            'razon_social' => $razon_social,
            'ruc' => $ruc,
        ]);
    }

    /**
     * Transportistas de mineral.
     */
    public static function get_transportistas(): array
    {
        return DB::table('transportista_mineral')->orderBy('razon_social', 'asc')->get()->all();
    }

    /**
     * Un transportista de mineral.
     */
    public static function get_transportista(int $id_transportista_mineral): ?object
    {
        return DB::table('transportista_mineral')->where('id', $id_transportista_mineral)->first();
    }

    /**
     * Indica si ya existe un transportista con esos datos.
     */
    public static function existe_transportista(string $razon_social, ?string $ruc): bool
    {
        $query = DB::table('transportista_mineral')->where('razon_social', $razon_social);

        if ($ruc !== null && $ruc !== '') {
            $query->where('ruc', $ruc);
        }

        return $query->exists();
    }

    /**
     * Registra un transportista de mineral.
     */
    public static function registrar_transportista(string $razon_social, ?string $ruc): int
    {
        return DB::table('transportista_mineral')->insertGetId([
            'razon_social' => $razon_social,
            'ruc' => $ruc,
        ]);
    }

    /**
     * Laboratorios.
     */
    public static function get_laboratorios(): array
    {
        return DB::table('laboratorio')->orderBy('razon_social', 'asc')->get()->all();
    }

    /**
     * Un laboratorio.
     */
    public static function get_laboratorio(int $id_laboratorio): ?object
    {
        return DB::table('laboratorio')->where('id', $id_laboratorio)->first();
    }

    /**
     * Indica si ya existe un laboratorio con esos datos.
     */
    public static function existe_laboratorio(string $razon_social, ?string $ruc): bool
    {
        $query = DB::table('laboratorio')->where('razon_social', $razon_social);

        if ($ruc !== null && $ruc !== '') {
            $query->where('ruc', $ruc);
        }

        return $query->exists();
    }

    /**
     * Registra un laboratorio.
     */
    public static function registrar_laboratorio(string $razon_social, ?string $ruc): int
    {
        return DB::table('laboratorio')->insertGetId([
            'razon_social' => $razon_social,
            'ruc' => $ruc,
        ]);
    }
}
