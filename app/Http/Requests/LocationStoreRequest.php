<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'code'         => ['required', 'string', 'max:50', 'unique:locations,code'],
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string'],
            'is_active'    => ['boolean'],
        ];
    }
}