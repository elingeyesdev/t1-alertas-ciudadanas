<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un paso de la línea de tiempo de un caso (HU-26). */
class CaseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['report_id', 'dispatch_id', 'type', 'detail'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class)->withTrashed();
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
}
