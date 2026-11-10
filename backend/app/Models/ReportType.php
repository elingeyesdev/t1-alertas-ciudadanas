<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportType extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'name', 'base_weight'];

    protected function casts(): array
    {
        return ['base_weight' => 'integer'];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
