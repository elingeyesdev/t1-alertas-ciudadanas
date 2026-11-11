<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'password',
        'refresh_token',
        'fcm_token',
        'last_latitude',
        'last_longitude',
        'role_id',
    ];

    protected $hidden = ['password', 'refresh_token', 'fcm_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function authorityProfile(): HasOne
    {
        return $this->hasOne(AuthorityProfile::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'creator_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'uploaded_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'creator_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class, 'attended_by');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }
}
