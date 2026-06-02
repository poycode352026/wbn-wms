<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'gr_number', 'pr_number', 'po_number', 'warehouse_id',
        'created_by', 'inspected_by', 'approved_by',
        'notes', 'status', 'auto_approved',
        'submitted_at', 'inspected_at', 'completed_at',
    ];

    protected $casts = [
        'submitted_at'  => 'datetime',
        'inspected_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'auto_approved' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GoodsReceiptPhoto::class)->orderBy('created_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public static function generateGrNumber(): string
    {
        $year  = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count();
        return 'GR-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
