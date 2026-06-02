<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'lv_code', 'lv_number', 'driver_id', 'type', 'department_id', 'is_active',
    ];

    /** Always append full_number to JSON serialization */
    protected $appends = ['full_number'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Full vehicle identifier: e.g. "WBN-LV-S81"
     */
    public function getFullNumberAttribute(): string
    {
        return ($this->lv_code ?? 'WBN-LV') . '-' . $this->lv_number;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function cooldowns(): HasMany
    {
        return $this->hasMany(CooldownLog::class, 'lv_id')->latest('cooldown_until');
    }
}
