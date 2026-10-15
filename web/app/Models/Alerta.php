<?php

namespace App\Models;

use Database\Factories\AlertaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tipo', 'descripcion', 'ubicacion', 'estado', 'fecha'])]
class Alerta extends Model
{
    /** @use HasFactory<AlertaFactory> */
    use HasFactory;

    protected $table = 'alertas';

    /**
     * Tipos de alerta disponibles (clave => etiqueta visible).
     */
    public const TIPOS = [
        'robo' => 'Robo',
        'accidente' => 'Accidente',
        'incendio' => 'Incendio',
        'emergencia_medica' => 'Emergencia médica',
        'violencia' => 'Violencia',
        'otro' => 'Otro',
    ];

    /**
     * Estados de atención de una alerta (clave => etiqueta visible).
     */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_atencion' => 'En atención',
        'atendida' => 'Atendida',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    /**
     * Filtra por tipo de alerta; si no se indica tipo, no filtra.
     */
    public function scopeDeTipo(Builder $query, ?string $tipo): Builder
    {
        return $query->when($tipo, fn (Builder $q) => $q->where('tipo', $tipo));
    }

    /**
     * Ordena de la más reciente a la más antigua.
     */
    public function scopeRecientesPrimero(Builder $query): Builder
    {
        return $query->orderByDesc('fecha')->orderByDesc('id');
    }

    public function getTipoEtiquetaAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst($this->estado);
    }
}
