<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingCaseContribution extends Model
{
    protected $fillable = ['user_id', 'message', 'photos', 'video_path'];

    protected function casts(): array
    {
        return ['photos' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
