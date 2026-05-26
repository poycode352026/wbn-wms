<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:20', 'unique:departments,code'],
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}