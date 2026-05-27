<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedger extends Model
{
    protected $fillable = [
        'item_variant_id', 'location_id', 'warehouse_id',
        'qty_on_hand', 'qty_reserved', 'last_updated_at',
    ];

    protected $casts = [
        'qty_on_hand'     => 'float',
        'qty_reserved'    => 'float',
        'qty_available'   => 'float',
        'last_updated_at' => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
