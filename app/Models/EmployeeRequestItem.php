<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeRequestItem extends Model
{
    protected $fillable = ['employee_request_id', 'item_id', 'lv_id', 'qty'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'employee_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lv(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'lv_id');
    }
}
