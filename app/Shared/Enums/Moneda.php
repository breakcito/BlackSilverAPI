<?php
/**
 * Enum de Monedas
 * Moneda::PEN->value; // "Soles"
 * Moneda::PEN->symbol(); // "S/"
 */
enum Moneda: string
{
    case PEN = 'Soles';
    case USD = 'Dólares';
    case EUR = 'Euros';

    public function symbol(): string
    {
        return match ($this) {
            self::PEN => 'S/',
            self::USD => '$',
            self::EUR => '€',
        };
    }
}