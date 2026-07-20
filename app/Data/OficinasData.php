<?php

namespace App\Data;

use App\Models\Oficina;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class OficinasData
{
    /**
     * Metodo generico para obtener oficinas
     */
    public static function get_oficinas(
        int|array|null $id_oficina = null,
        int|array|null $id_empresa = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $query = Oficina::query()
            ->select([
                'oficina.id as id_oficina',
                'oficina.id_empresa',
                'empresa.razon_social as empresa',
                'oficina.nombre',
                'oficina.direccion',
                'oficina.es_principal',
                'oficina.estado',
            ])
            ->join('empresa', 'empresa.id', '=', 'oficina.id_empresa')

            ->when($id_oficina !== null, function ($q) use ($id_oficina) {
                is_array($id_oficina)
                    ? $q->whereIn('oficina.id', $id_oficina)
                    : $q->where('oficina.id', $id_oficina);
            })

            ->when($id_empresa !== null, function ($q) use ($id_empresa) {
                is_array($id_empresa)
                    ? $q->whereIn('oficina.id_empresa', $id_empresa)
                    : $q->where('oficina.id_empresa', $id_empresa);
            })

            ->when(
                $estado !== null,
                fn($q) =>
                $q->where('oficina.estado', $estado->value)
            )

            ->orderByDesc('oficina.es_principal')
            ->orderBy('oficina.nombre');

        return is_int($id_oficina)
            ? $query->first()
            : $query->get();
    }

    /**
     * Registrar oficina
     */
    public static function crear_oficina(
        int $id_empresa,
        string $nombre,
        ?string $direccion,
        bool $es_principal = false
    ) {
        return Oficina::insertGetId([
            'id_empresa' => $id_empresa,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'es_principal' => $es_principal ? 1 : 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si existe la oficina
     */
    public static function ya_existe(int $id_empresa, string $nombre): bool
    {
        return Oficina::where('id_empresa', $id_empresa)
            ->where('nombre', $nombre)
            ->exists();
    }
}
