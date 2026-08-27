<?php

namespace App\Data;

use App\Models\Transportista;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class TransportistasData
{
    /**
     * Lista transportistas activos, con filtro opcional por id.
     */
    public static function get_transportistas(
        ?int $id_transportista = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ) {
        $sql = '
            SELECT
                t.id AS id_transportista,
                t.tipo_entidad,
                t.razon_social,
                t.ruc,
                t.dni,
                t.telefono,
                t.estado
            FROM transportista t
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_transportista !== null) {
            $sql .= ' AND t.id = :id_transportista';
            $params['id_transportista'] = $id_transportista;
            return DB::selectOne($sql, $params);
        }

        if ($estado !== null) {
            $sql .= ' AND t.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY t.razon_social ASC';

        return DB::select($sql, $params);
    }

    public static function existe_transportista(
        string $razon_social,
        ?string $ruc,
        ?string $dni,
    ): bool {
        $q = Transportista::query();
        $q->where('razon_social', $razon_social);
        if (!empty($ruc)) {
            $q->where('ruc', $ruc);
        }
        if (!empty($dni)) {
            $q->where('dni', $dni);
        }
        return $q->exists();
    }

    public static function crear_transportista(
        string $tipo_entidad,
        string $razon_social,
        ?string $ruc,
        ?string $dni,
        ?string $telefono,
    ): int {
        return Transportista::insertGetId([
            'tipo_entidad' => $tipo_entidad,
            'razon_social' => $razon_social,
            'ruc' => $ruc ?? '',
            'dni' => $dni ?? '',
            'telefono' => $telefono ?? '',
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
