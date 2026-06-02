<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Location;

class Warehouse extends Model
{
    protected $fillable = ['code', 'name', 'location', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
