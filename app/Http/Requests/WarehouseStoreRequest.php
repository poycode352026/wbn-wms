<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:20', 'unique:warehouses,code'],
            'name'      => ['required', 'string', 'max:100'],
            'location'  => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}