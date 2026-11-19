<?php

namespace App\Shared\Enums\Balances;

/**
 * Categorias de movimiento que integran el balance.
 *
 * Cada origen identifica UN registro de una tabla fisica (o el agregado de
 * una familia de registros) y por eso se mantiene el enum completo con su
 * ambito, su tipo y su etiqueta legible: el front usa esta metadata para
 * los filtros y los titulos del desglose, sin duplicar catálogos.
 */
enum OrigenBalance: string
{
    // MINERAL - EGRESOS
    case ConsumoMineral = 'CONSUMO_MINERAL';
    case GastoExtra = 'GASTO_EXTRA';
    case Mantenimiento = 'MANTENIMIENTO';
    case GastoDespacho = 'GASTO_DESPACHO';
    case FleteDespacho = 'FLETE_DESPACHO';
    case MoliendaDespacho = 'MOLIENDA_DESPACHO';
    case AnalisisDespacho = 'ANALISIS_DESPACHO';

    // MINERAL - INGRESOS
    case AlquilerMaquinaria = 'ALQUILER_MAQUINARIA';
    case VentaMineral = 'VENTA_MINERAL';

    // CARBON - EGRESOS
    case CompraCarbon = 'COMPRA_CARBON';
    case FleteCompraCarbon = 'FLETE_COMPRA_CARBON';
    case GastoProduccionCarbon = 'GASTO_PRODUCCION_CARBON';

    // CARBON - INGRESOS
    case VentaCarbon = 'VENTA_CARBON';

    /**
     * Ambito al que pertenece el origen.
     */
    public function ambito(): AmbitoBalance
    {
        return match ($this) {
            self::ConsumoMineral,
            self::GastoExtra,
            self::Mantenimiento,
            self::GastoDespacho,
            self::FleteDespacho,
            self::MoliendaDespacho,
            self::AnalisisDespacho,
            self::AlquilerMaquinaria,
            self::VentaMineral => AmbitoBalance::Mineral,
            self::CompraCarbon,
            self::FleteCompraCarbon,
            self::GastoProduccionCarbon,
            self::VentaCarbon => AmbitoBalance::Carbon,
        };
    }

    /**
     * Signo del movimiento: si suma o resta al balance.
     */
    public function tipo(): TipoBalance
    {
        return match ($this) {
            self::AlquilerMaquinaria,
            self::VentaMineral,
            self::VentaCarbon => TipoBalance::Ingreso,
            default => TipoBalance::Egreso,
        };
    }

    /**
     * Etiqueta legible para el desglose.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::ConsumoMineral => 'Consumo de inventario',
            self::GastoExtra => 'Gastos extra',
            self::Mantenimiento => 'Mantenimiento de maquinaria',
            self::GastoDespacho => 'Gastos de despacho',
            self::FleteDespacho => 'Flete de despacho',
            self::MoliendaDespacho => 'Molienda',
            self::AnalisisDespacho => 'Analisis de laboratorio',
            self::AlquilerMaquinaria => 'Alquiler de maquinaria',
            self::VentaMineral => 'Venta de mineral',
            self::CompraCarbon => 'Compra de carbon',
            self::FleteCompraCarbon => 'Flete de compra de carbon',
            self::GastoProduccionCarbon => 'Gastos de produccion de carbon',
            self::VentaCarbon => 'Venta de carbon',
        };
    }

    /**
     * Valores permitidos, para usarlos en reglas de validacion.
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }

    /**
     * Catalogos que consume el front para pintar filtros y titulos.
     *
     * @return array<int, array{value: string, label: string, ambito: string, tipo: string}>
     */
    public static function catalogo(): array
    {
        return array_map(fn ($c) => [
            'value' => $c->value,
            'label' => $c->etiqueta(),
            'ambito' => $c->ambito()->value,
            'tipo' => $c->tipo()->value,
        ], self::cases());
    }
}
