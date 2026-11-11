<?php

namespace App\Models;

use App\Enums\StateReport;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'description',
        'latitude',
        'longitude',
        'weight',
        'zone',
        'verified',
        'expires_at',
        'status',
        'creator_id',
        'report_type_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'weight' => 'integer',
            'verified' => 'boolean',
            'expires_at' => 'datetime',
            'status' => StateReport::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withTrashed();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ReportType::class, 'report_type_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    /** Reportes dentro de un radio (metros) de un punto. */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $meters): Builder
    {
        return Geo::whereWithin($query, $lat, $lng, $meters, 'reports.latitude', 'reports.longitude');
    }
}
