<?php

namespace App\Shared\Helpers;

use Illuminate\Support\Facades\DB;

class CorrelativoHelper
{
    /**
     * Genera un correlativo con formato combinando prefijo, año (opcional) y un número autoincremental.
     * Ejemplos de resultado: 
     * - "LOT-26-00001"
     * - "CH-26-0001"
     * - "GAL-001"
     * 
     * @param string $tabla Nombre de la tabla
     * @param string $columna Columna principal donde buscar/guardar el código completo (ej: 'codigo_correlativo').
     *                      Si es null, buscará por el máximo en la columna $columnaNumero.
     * @param string $prefijo El prefijo a poner (Ej: "LOT", "CH", "GAL")
     * @param int $longitudCeros Cuántos ceros poner antes del número (ej: 4 para 0001)
     * @param bool $incluirYear Si debe incluir el año actual (-YY-)
     * @param string|null $columnaNumero (Opcional) Si la tabla usa una columna separada solo para el INT (ej: 'numero_correlativo' en lotes)
     */
    public static function generar(
        string $tabla, 
        ?string $columna = 'codigo_correlativo', 
        string $prefijo = '', 
        int $longitudCeros = 4,
        bool $incluirYear = true,
        ?string $columnaNumero = null,
        ?string $columnaFecha = null
    ): string {
        
        $query = DB::table($tabla);
        $siguienteNumero = 1;
        $yearStr = date('y');

        // Si incluye año y la tabla tiene columna de fecha, filtramos por el año actual
        if ($incluirYear && $columnaFecha) {
            $query->whereYear($columnaFecha, date('Y'));
        }

        // LÓGICA 1: Si hay una columna explícita para el número (Como en LoteProducto: 'numero_correlativo')
        if ($columnaNumero) {
            $maximoActual = $query->max($columnaNumero) ?? 0;
            $siguienteNumero = $maximoActual + 1;
        } 
        // LÓGICA 2: Si todo el correlativo está en un solo campo VARCHAR (Como en Labor: "CH-26-0001")
        else if ($columna) {
            // Filtramos únicamente los registros que coinciden con nuestro prefijo y año actual
            $busqueda = $incluirYear ? "{$prefijo}-{$yearStr}-%" : "{$prefijo}-%";
            $query->where($columna, 'like', $busqueda);

            $ultimoRegistro = $query->orderBy('id', 'desc')->first();

            if ($ultimoRegistro && isset($ultimoRegistro->$columna)) {
                // Separamos por guiones ("CH-26-0001" -> ["CH", "26", "0001"])
                $partes = explode('-', $ultimoRegistro->$columna);
                // El número siempre debe ser la última parte
                $ultimoNumeroStr = end($partes);
                $siguienteNumero = ((int) $ultimoNumeroStr) + 1;
            }
        }

        // Formatear a la cantidad de ceros pedida (ej: 0001, 00001)
        $numeroConCeros = str_pad($siguienteNumero, $longitudCeros, "0", STR_PAD_LEFT);

        // Armamos el string final concatenando
        if ($incluirYear) {
            return "{$prefijo}-{$yearStr}-{$numeroConCeros}";
        }

        // Si no lleva año
        return "{$prefijo}-{$numeroConCeros}";
    }

    /**
     * Solo retorna el INT siguiente (Útil para LoteProducto donde necesitan almacenar el número aparte)
     */
    public static function getNuevoNumero(string $tabla, string $columnaNumero, bool $reiniciarPorAnio = true, ?string $columnaFecha = 'created_at'): int
    {
        $query = DB::table($tabla);
        if ($reiniciarPorAnio && $columnaFecha) {
            $query->whereYear($columnaFecha, date('Y'));
        }
        $maximoActual = $query->max($columnaNumero) ?? 0;
        return $maximoActual + 1;
    }
}
