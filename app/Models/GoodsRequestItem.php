<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsRequestItem extends Model
{
    protected $fillable = [
        'goods_request_id',
        'item_variant_id',
        'warehouse_id',
        'location_id',
        'requested_qty',
        'uom_used',
        'qty_in_base_uom',
        'actual_qty',
        'item_status',
        'notes',
    ];

    protected $casts = [
        'requested_qty'   => 'float',
        'qty_in_base_uom' => 'float',
        'actual_qty'      => 'float',
    ];

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
