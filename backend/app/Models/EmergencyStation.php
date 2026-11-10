<?php

namespace App\Models;

use App\Enums\InstallationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyStation extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = ['name', 'installation_type', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'installation_type' => InstallationType::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class, 'destination_id');
    }
}
