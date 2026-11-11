<?php

namespace App\Enums;

enum InstallationType: string
{
    case Policia = 'policia';
    case Bombero = 'bombero';
    case Hospital = 'hospital';
}
