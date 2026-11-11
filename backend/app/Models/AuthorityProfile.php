<?php

namespace App\Models;

use App\Enums\ProfileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorityProfile extends Model
{
    public $timestamps = false;

    protected $fillable = ['ci', 'gmail', 'profile_type', 'user_id'];

    protected function casts(): array
    {
        return ['profile_type' => ProfileType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
