<?php

namespace App\Models;

use App\Enums\StateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispatch extends Model
{
    public $timestamps = false;

    protected $fillable = ['recorded_at', 'state', 'report_id', 'destination_id', 'attended_by'];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'state' => StateType::class,
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class)->withTrashed();
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(EmergencyStation::class, 'destination_id')->withTrashed();
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by')->withTrashed();
    }
}
