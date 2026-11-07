<?php

namespace App\Modules\VentaMineral\Service;

use App\Modules\VentaMineral\Data\VentaMineralCatalogoData;
use App\Shared\Responses\ApiResponse;

/**
 * Orquestacion de los catalogos del modulo de venta de mineral: molinos,
 * transportistas de mineral y laboratorios.
 */
class VentaMineralCatalogoService
{
    /**
     * Lista los molinos.
     */
    public static function get_molinos(): array
    {
        return ApiResponse::success(VentaMineralCatalogoData::get_molinos());
    }

    /**
     * Un molino.
     */
    public static function get_molino(int $id_molino): ?object
    {
        return VentaMineralCatalogoData::get_molino($id_molino);
    }

    /**
     * Registra un molino.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_molino(array $payload): array
    {
        $razonSocial = trim((string) ($payload['razon_social'] ?? ''));
        $ruc = trim((string) ($payload['ruc'] ?? ''));

        $error = self::validar_identidad($razonSocial, $ruc);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        if (VentaMineralCatalogoData::existe_molino($razonSocial, $ruc === '' ? null : $ruc)) {
            return ApiResponse::error('Ya existe un molino con esos datos', 409);
        }

        $id = VentaMineralCatalogoData::registrar_molino(
            $razonSocial,
            $ruc === '' ? null : $ruc
        );

        return ApiResponse::success(
            VentaMineralCatalogoData::get_molino($id),
            'Molino registrado correctamente'
        );
    }

    /**
     * Lista los transportistas de mineral.
     */
    public static function get_transportistas(): array
    {
        return ApiResponse::success(VentaMineralCatalogoData::get_transportistas());
    }

    /**
     * Un transportista de mineral.
     */
    public static function get_transportista(int $id_transportista_mineral): ?object
    {
        return VentaMineralCatalogoData::get_transportista($id_transportista_mineral);
    }

    /**
     * Registra un transportista de mineral.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_transportista(array $payload): array
    {
        $razonSocial = trim((string) ($payload['razon_social'] ?? ''));
        $ruc = trim((string) ($payload['ruc'] ?? ''));

        $error = self::validar_identidad($razonSocial, $ruc);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        if (VentaMineralCatalogoData::existe_transportista($razonSocial, $ruc === '' ? null : $ruc)) {
            return ApiResponse::error('Ya existe un transportista con esos datos', 409);
        }

        $id = VentaMineralCatalogoData::registrar_transportista(
            $razonSocial,
            $ruc === '' ? null : $ruc
        );

        return ApiResponse::success(
            VentaMineralCatalogoData::get_transportista($id),
            'Transportista registrado correctamente'
        );
    }

    /**
     * Lista los laboratorios.
     */
    public static function get_laboratorios(): array
    {
        return ApiResponse::success(VentaMineralCatalogoData::get_laboratorios());
    }

    /**
     * Un laboratorio.
     */
    public static function get_laboratorio(int $id_laboratorio): ?object
    {
        return VentaMineralCatalogoData::get_laboratorio($id_laboratorio);
    }

    /**
     * Registra un laboratorio.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_laboratorio(array $payload): array
    {
        $razonSocial = trim((string) ($payload['razon_social'] ?? ''));
        $ruc = trim((string) ($payload['ruc'] ?? ''));

        $error = self::validar_identidad($razonSocial, $ruc);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        if (VentaMineralCatalogoData::existe_laboratorio($razonSocial, $ruc === '' ? null : $ruc)) {
            return ApiResponse::error('Ya existe un laboratorio con esos datos', 409);
        }

        $id = VentaMineralCatalogoData::registrar_laboratorio(
            $razonSocial,
            $ruc === '' ? null : $ruc
        );

        return ApiResponse::success(
            VentaMineralCatalogoData::get_laboratorio($id),
            'Laboratorio registrado correctamente'
        );
    }

    /**
     * Valida la razon social y el RUC de un catalogo.
     */
    private static function validar_identidad(string $razonSocial, string $ruc): ?string
    {
        if ($razonSocial === '') {
            return 'La razon social es obligatoria';
        }

        if (mb_strlen($razonSocial) < 3) {
            return 'La razon social debe tener al menos 3 caracteres';
        }

        if ($ruc !== '' && !preg_match('/^\d{11}$/', $ruc)) {
            return 'El RUC debe tener exactamente 11 digitos';
        }

        return null;
    }
}
