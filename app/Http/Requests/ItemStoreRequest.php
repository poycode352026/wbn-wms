<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role, ['super_admin']);
    }

    public function rules(): array
    {
        return [
            'category_id'        => ['required', 'exists:item_categories,id'],
            'part_number'        => ['required', 'string', 'max:100', 'unique:items,part_number'],
            'name_en'            => ['required', 'string', 'max:200'],
            'name_id'            => ['nullable', 'string', 'max:200'],
            'name_zh'            => ['nullable', 'string', 'max:200'],
            'description'        => ['nullable', 'string'],
            'base_uom'           => ['required', 'string', 'max:50'],
            'alt_uom'            => ['nullable', 'string', 'max:50'],
            'alt_uom_conversion' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock'      => ['required', 'numeric', 'min:0'],
            'has_cooldown'       => ['boolean'],
            'photo_required'     => ['boolean'],
            'cooldown_days'      => ['nullable', 'integer', 'min:1'],
            'cooldown_track_by'  => ['nullable', Rule::in(['lv_number', 'employee_id'])],
            'is_active'          => ['boolean'],
        ];
    }
}