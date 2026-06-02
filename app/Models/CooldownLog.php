<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooldownLog extends Model
{
    protected $fillable = [
        'item_id', 'item_variant_id', 'track_type',
        'lv_id', 'employee_id', 'goods_issue_id',
        'taken_at', 'cooldown_until',
    ];

    protected $casts = [
        'taken_at'      => 'date',
        'cooldown_until'=> 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }
}
