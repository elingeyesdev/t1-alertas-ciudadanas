<?php

namespace App\Enums;

enum StateType: string
{
    case EnCurso = 'en_curso';
    case Completado = 'completado';
    case Cancelado = 'cancelado';
}
