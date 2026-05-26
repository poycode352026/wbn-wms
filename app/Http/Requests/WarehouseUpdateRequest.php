<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        $id = $this->route('warehouse')?->id;
        return [
            'code'      => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($id)],
            'name'      => ['required', 'string', 'max:100'],
            'location'  => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}