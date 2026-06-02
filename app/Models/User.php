<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'employee_id',
        'email',
        'password',
        'role',
        'extra_roles',
        'is_active',
        'department_id',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'extra_roles'       => 'array',
        ];
    }

    /**
     * Check if the user has a given role (primary or extra).
     */
    public function hasRole(string $role): bool
    {
        if ($this->role === $role) return true;
        return in_array($role, $this->extra_roles ?? []);
    }

    /**
     * Check if the user has operator portal access.
     */
    public function isOperator(): bool
    {
        return $this->hasRole('operator');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}