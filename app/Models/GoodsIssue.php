<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsIssue extends Model
{
    protected $fillable = [
        'gi_number', 'warehouse_id', 'department_id', 'requested_by',
        'project', 'purpose', 'usage_location', 'status', 'notes',
        'assigned_to', 'picked_by',
        'submitted_at', 'picked_at', 'completed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'submitted_at'  => 'datetime',
        'picked_at'     => 'datetime',
        'completed_at'  => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pickedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsIssueItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(GoodsIssueApproval::class)->orderBy('acted_at');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GoodsIssuePhoto::class)->orderBy('created_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public static function generateGiNumber(): string
    {
        $year  = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count();
        return 'GI-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
