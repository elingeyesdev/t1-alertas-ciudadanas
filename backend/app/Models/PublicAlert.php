<?php

namespace App\Models;

use App\Enums\PublicAlertCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** HU-32: aviso preventivo (salud, viaje o desastre natural) para una zona. */
class PublicAlert extends Model
{
    use SoftDeletes;

    protected $fillable = ['category', 'title', 'message', 'latitude', 'longitude', 'radius_m', 'expires_at', 'created_by', 'notified_count'];

    protected function casts(): array
    {
        return [
            'category' => PublicAlertCategory::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'notified_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Avisos vigentes: todavía no llegó su fecha de fin. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
