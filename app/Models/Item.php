<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id', 'category_id', 'part_number',
        'name_id', 'name_en', 'name_zh', 'description',
        'base_uom', 'alt_uom', 'alt_uom_conversion',
        'minimum_stock', 'has_cooldown', 'cooldown_days', 'cooldown_track_by',
        'photo_required', 'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'has_cooldown'       => 'boolean',
        'photo_required'     => 'boolean',
        'alt_uom_conversion' => 'decimal:4',
        'minimum_stock'      => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }
}