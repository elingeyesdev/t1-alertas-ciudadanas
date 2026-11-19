<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contacto del círculo de confianza de una persona (HU-25). */
class TrustContact extends Model
{
    protected $fillable = ['owner_id', 'contact_user_id', 'name', 'phone'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function hasApp(): bool
    {
        return $this->contact_user_id !== null;
    }
}
