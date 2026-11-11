<?php

namespace App\Enums;

enum StateReport: string
{
    case Activo = 'activo';
    case Resuelto = 'resuelto';
    case Vencido = 'vencido';
    case Eliminado = 'eliminado';
}
