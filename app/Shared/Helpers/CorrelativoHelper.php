<?php

namespace App\Shared\Helpers;

use Illuminate\Support\Facades\DB;

class CorrelativoHelper
{
    /**
     * Genera un código correlativo robusto con formato {PREFIJO}-{YY}-{NUMERO}.
     * Soporta reseteo anual y filtros adicionales (ej: por empresa).
     * 
     * @param string $tabla 
     * @param string $columnaIdentificadora Columna VARCHAR (ej: 'codigo_correlativo')
     * @param string $prefijo (ej: 'LOT', 'CH')
     * @param int $longitudCeros Padding (ej: 4 -> 0001)
     * @param array $filtros Filtros adicionales [columna => valor] (ej: ['id_empresa' => 5])
     * @param string $columnaFecha 
     */
    public static function generar(
        string $tabla,
        string $columnaIdentificadora,
        string $prefijo,
        int $longitudCeros = 4,
        array $filtros = [],
        string $columnaFecha = 'created_at'
    ): string {
        $yearActual = date('Y');
        $yearShort = date('y');
        
        $query = DB::table($tabla)->whereYear($columnaFecha, $yearActual);

        // Aplicar filtros adicionales (ej: id_empresa)
        foreach ($filtros as $col => $val) {
            $query->where($col, $val);
        }

        $siguienteNumero = 1;

        // LÓGICA DE EXTRACCIÓN: Solo tenemos el VARCHAR (ej: "CH-26-0001")
        $patron = "{$prefijo}-{$yearShort}-%";
        
        $ultimo = (clone $query)
            ->where($columnaIdentificadora, 'like', $patron)
            ->orderBy('id', 'desc')
            ->first();

        if ($ultimo) {
            $valorCompleto = $ultimo->{$columnaIdentificadora};
            $partes = explode('-', $valorCompleto);
            $ultimaParte = end($partes);
            $siguienteNumero = (int)$ultimaParte + 1;
        }

        $numeroFormateado = str_pad($siguienteNumero, $longitudCeros, "0", STR_PAD_LEFT);
        
        return "{$prefijo}-{$yearShort}-{$numeroFormateado}";
    }

    /**
     * Obtiene el siguiente número entero para una columna dedicada.
     * Soporta reseteo anual y filtros adicionales.
     */
    public static function proximoNumero(
        string $tabla, 
        string $columnaNumero, 
        bool $reseteoAnual = true, 
        array $filtros = [], 
        string $columnaFecha = 'created_at'
    ): int {
        $query = DB::table($tabla);
        
        if ($reseteoAnual) {
            $query->whereYear($columnaFecha, date('Y'));
        }

        foreach ($filtros as $col => $val) {
            $query->where($col, $val);
        }

        return ($query->max($columnaNumero) ?? 0) + 1;
    }
}
