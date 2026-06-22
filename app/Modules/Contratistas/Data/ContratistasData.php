<?php

namespace App\Modules\Contratistas\Data;

use App\Models\Empleado;
use App\Models\LaborContratista;
use Illuminate\Support\Facades\DB;

class ContratistasData
{
    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(
        ?int $id_mina = null,
        ?int $id_contratista = null
    ) {
        $sql = '
        SELECT 
            c.id AS id_contratista,

            c.id_mina,
            mn.nombre AS mina,

            c.nombre,
            c.apellido,
            c.dni,
            c.ruc,
            c.carnet_extranjeria,
            c.pasaporte,
            c.fecha_nacimiento,
            c.url_foto as url_foto,

            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "id_labor_contratista", lc.id,
                        "id_labor", lab.id,
                        "correlativo", lab.correlativo,
                        "nombre", lab.nombre
                    )
                )
                FROM labor_contratista lc
                INNER JOIN labor lab ON lab.id = lc.id_labor
                WHERE lc.id_contratista = c.id
            ) AS labores_asignadas,

            c.estado

        FROM empleado c
        LEFT JOIN mina mn ON mn.id = c.id_mina
        WHERE c.es_contratista = 1
        ';

        $params = [];

        if ($id_contratista) {
            $sql .= ' AND c.id = :id_contratista';
            $params['id_contratista'] = $id_contratista;

            $contratista = DB::selectOne($sql, $params);
            if (!$contratista) {
                return null;
            }
            $contratista->labores_asignadas = json_decode($contratista->labores_asignadas, true);
            return $contratista;
        }

        if ($id_mina !== null) {
            $sql .= ' AND c.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY c.apellido ASC, c.nombre ASC';

        $contratistas = DB::select($sql, $params);
        foreach ($contratistas as $contratista) {
            $contratista->labores_asignadas = json_decode($contratista->labores_asignadas, true);
        }
        return $contratistas;
    }


    /**
     * Actualizar la foto de un contratista
     */
    public static function actualizar_foto(int $id_contratista, ?string $url_foto): bool
    {
        return (bool) Empleado::where('id', $id_contratista)->update(['url_foto' => $url_foto]);
    }


    /**
     * Metodo para consultar datos dinamicos de uno o varios contratistas a la vez
     */
    public static function get_contratista_dinamico_by_id(int|array $id_contratista, array $columnas): ?array
    {
        $esArray = is_array($id_contratista);
        $ids = $esArray ? $id_contratista : [$id_contratista];
        // Forzamos la inclusión del ID con su alias
        if (!in_array('id as id_contratista', $columnas)) {
            $columnas[] = 'id as id_contratista';
        }
        $query = Empleado::where('es_contratista', 1)->whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }
        return $query->first()?->toArray();
    }


    /**
     * Eliminar todas las labores asignadas a un contratista
     */
    public static function eliminar_labores_asignadas(int $id_contratista): void
    {
        LaborContratista::where('id_contratista', $id_contratista)->delete();
    }

    /**
     * Actualizar mina del contratista
     */
    public static function update_mina(int $id_contratista, int $id_mina)
    {
        return Empleado::where('id', $id_contratista)->update(['id_mina' => $id_mina]);
    }
}
