<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsIssueItem extends Model
{
    protected $fillable = [
        'goods_issue_id', 'item_variant_id', 'item_warehouse_id',
        'requested_qty', 'requested_uom', 'qty_in_base_uom', 'uom_used',
        'actual_qty', 'item_reason', 'store_to',
        'lv_id', 'employee_id', 'cooldown_until',
        'notes', 'status',
    ];

    protected $casts = [
        'cooldown_until' => 'date',
    ];

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function lv(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'lv_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function itemWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'item_warehouse_id');
    }
}
