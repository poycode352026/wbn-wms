<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsRequestPhoto extends Model
{
    protected $fillable = [
        'goods_request_id',
        'path',
        'original_name',
        'stage',
        'uploaded_by',
    ];

    public function goodsRequest(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
