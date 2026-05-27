<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemVariant extends Model
{
    use SoftDeletes;

    protected $fillable = ['item_id', 'brand', 'model', 'size', 'color', 'sku', 'photo_path', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}