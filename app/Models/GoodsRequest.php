<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsRequest extends Model
{
    protected $fillable = [
        'grq_number',
        'warehouse_id',
        'requester_name',
        'requester_emp_id',
        'department_id',
        'remark',
        'recorded_by',
        'status',
        'assigned_to',
        'assigned_at',
        'picked_by',
        'picked_at',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'assigned_at'  => 'datetime',
        'picked_at'    => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public static function generateGrqNumber(): string
    {
        $year  = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count();
        return 'GRQ-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pickedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsRequestItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GoodsRequestPhoto::class);
    }
}
