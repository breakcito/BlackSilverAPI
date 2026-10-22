<?php

namespace App\Modules\TipoCarbon\Data;

use App\Models\CalidadCarbon;
use Illuminate\Support\Facades\DB;

class CalidadCarbonData
{
    /**
     * Lista las calidades ordenadas de menor a mayor rango de ceniza.
     * @return array<object>
     */
    public static function get_calidades(): array
    {
        $sql = '
            SELECT
                c.id AS id_calidad_carbon,
                c.nombre,
                c.porcentaje_inicio_ceniza,
                c.porcentaje_fin_ceniza
            FROM calidad_carbon c
            ORDER BY c.porcentaje_inicio_ceniza ASC, c.id ASC
        ';

        return DB::select($sql);
    }

    /**
     * Obtiene una calidad por su id.
     */
    public static function get_calidad_by_id(int $id_calidad_carbon): ?object
    {
        $sql = '
            SELECT
                c.id AS id_calidad_carbon,
                c.nombre,
                c.porcentaje_inicio_ceniza,
                c.porcentaje_fin_ceniza
            FROM calidad_carbon c
            WHERE c.id = :id
            LIMIT 1
        ';

        return DB::selectOne($sql, ['id' => $id_calidad_carbon]);
    }

    /**
     * Actualiza el rango de ceniza de una calidad.
     */
    public static function actualizar_calidad(
        int $id_calidad_carbon,
        float $porcentaje_inicio_ceniza,
        float $porcentaje_fin_ceniza
    ): int {
        return CalidadCarbon::where('id', $id_calidad_carbon)->update([
            'porcentaje_inicio_ceniza' => $porcentaje_inicio_ceniza,
            'porcentaje_fin_ceniza' => $porcentaje_fin_ceniza,
        ]);
    }

    /**
     * Resuelve la calidad que corresponde a un porcentaje de ceniza.
     *
     * Cuando un valor cae en el borde shared entre dos rangos (ej. 15% que
     * es el fin de "Baja" y el inicio de "Intermedia") gana la calidad con el
     * `porcentaje_inicio_ceniza` mas alto, es decir la mas restrictiva.
     * Si no hay coincidencia exacta se cae en la calidad de mayor inicio
     * (la que representa "Alta", sin limite superior real).
     */
    public static function resolver_por_ceniza(float $porcentaje_ceniza): ?object
    {
        // Nota: PDO con prepares nativos (emulate_prepares = false) no admite
        // reutilizar el mismo placeholder nombrado, por eso se usan dos
        // nombres distintos para el mismo valor.
        $sql = '
            SELECT
                c.id AS id_calidad_carbon,
                c.nombre,
                c.porcentaje_inicio_ceniza,
                c.porcentaje_fin_ceniza
            FROM calidad_carbon c
            WHERE :ceniza_min >= c.porcentaje_inicio_ceniza
              AND :ceniza_max <= c.porcentaje_fin_ceniza
            ORDER BY c.porcentaje_inicio_ceniza DESC
            LIMIT 1
        ';

        $calidad = DB::selectOne($sql, [
            'ceniza_min' => $porcentaje_ceniza,
            'ceniza_max' => $porcentaje_ceniza,
        ]);

        if ($calidad !== null) {
            return $calidad;
        }

        // Sin coincidencia exacta (ceniza por encima del maximo configurado):
        // se usa la calidad con mayor rango de inicio.
        return DB::selectOne('
            SELECT
                c.id AS id_calidad_carbon,
                c.nombre,
                c.porcentaje_inicio_ceniza,
                c.porcentaje_fin_ceniza
            FROM calidad_carbon c
            ORDER BY c.porcentaje_inicio_ceniza DESC
            LIMIT 1
        ');
    }
}
