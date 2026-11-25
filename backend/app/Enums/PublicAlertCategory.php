<?php

namespace App\Enums;

/** HU-32: tipos de aviso preventivo que publica el administrador. */
enum PublicAlertCategory: string
{
    case Salud = 'salud';
    case Viaje = 'viaje';
    case Desastre = 'desastre';

    public function label(): string
    {
        return match ($this) {
            self::Salud => 'Salud',
            self::Viaje => 'Viaje',
            self::Desastre => 'Desastre natural',
        };
    }
}
