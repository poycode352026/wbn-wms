<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id', 'employee_id', 'name', 'department_id', 'position', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function drivenVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_id');
    }

    public function cooldowns(): HasMany
    {
        return $this->hasMany(CooldownLog::class, 'employee_id')->latest('cooldown_until');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EmployeeRequest::class);
    }
}
