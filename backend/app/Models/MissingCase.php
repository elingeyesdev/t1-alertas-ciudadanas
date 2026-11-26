<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissingCase extends Model
{
    public const KINDS = ['person' => 'Persona', 'pet' => 'Mascota'];

    public const STATUSES = [
        'pending' => 'Pendiente',
        'published' => 'Publicado',
        'rejected' => 'Rechazado',
        'found' => 'Encontrado',
    ];

    protected $fillable = ['kind', 'name', 'place', 'missing_on', 'description', 'reference_phone', 'photos'];

    protected function casts(): array
    {
        return [
            'missing_on' => 'date',
            'photos' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'published_at' => 'datetime',
            'found_at' => 'datetime',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopeVisibleOnWall(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'found']);
    }

    public function scopeOnMap(Builder $query): Builder
    {
        return $query->visibleOnWall()->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query->visibleOnWall()->orWhere('submitted_by', $user->id));
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(MissingCaseContribution::class);
    }
}
