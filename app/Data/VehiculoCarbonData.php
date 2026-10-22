<?php

namespace App\Data;

use App\Models\VehiculoCarbon;
use Illuminate\Support\Facades\DB;

/**
 * Catalogo global de vehiculos usados en el despacho de carbon.
 *
 * `vehiculo_carbon` no tiene columna `estado` (baja logica): un vehiculo
 * desaparece del catalogo solo si el usuario lo elimina.
 */
class VehiculoCarbonData
{
    /**
     * Lista los vehiculos con su marca.
     * @return array<object>
     */
    public static function get_vehiculos(?int $id_vehiculo_carbon = null, ?string $filtros = null): array
    {
        $sql = '
            SELECT
                v.id AS id_vehiculo_carbon,
                v.id_marca,
                m.nombre AS marca,
                v.tipo_vehiculo,
                v.placa
            FROM vehiculo_carbon v
            LEFT JOIN marca m ON m.id = v.id_marca
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_vehiculo_carbon !== null && $id_vehiculo_carbon > 0) {
            $sql .= ' AND v.id = :id_vehiculo_carbon';
            $params['id_vehiculo_carbon'] = $id_vehiculo_carbon;
        }

        $filtros = trim((string) $filtros);
        if ($filtros !== '') {
            $sql .= ' AND (v.placa LIKE :q OR m.nombre LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY v.placa ASC';

        return DB::select($sql, $params);
    }

    /**
     * Inserta un vehiculo. La placa se guarda en mayusculas para evitar
     * duplicados por diferencia de caja.
     */
    public static function crear_vehiculo(int $id_marca, string $tipo_vehiculo, string $placa): int
    {
        return VehiculoCarbon::insertGetId([
            'id_marca' => $id_marca > 0 ? $id_marca : null,
            'tipo_vehiculo' => $tipo_vehiculo,
            'placa' => strtoupper(trim($placa)),
        ]);
    }

    /**
     * Verifica si ya existe un vehiculo con la misma marca, tipo y placa.
     */
    public static function existe_vehiculo(int $id_marca, string $tipo_vehiculo, string $placa): bool
    {
        $row = DB::table('vehiculo_carbon')
            ->where('placa', strtoupper(trim($placa)))
            ->where('tipo_vehiculo', $tipo_vehiculo)
            ->when(
                $id_marca > 0,
                fn ($q) => $q->where('id_marca', $id_marca),
                fn ($q) => $q->whereNull('id_marca')
            )
            ->first();

        return $row !== null;
    }
}
