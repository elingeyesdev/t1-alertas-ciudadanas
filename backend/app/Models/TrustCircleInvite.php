<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de invitación al círculo de confianza (HU-25). */
class TrustCircleInvite extends Model
{
    protected $fillable = ['owner_id', 'code', 'expires_at', 'used_at', 'used_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
