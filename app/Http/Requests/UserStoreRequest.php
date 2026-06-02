<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'employee_id'   => ['required', 'string', 'max:50', 'unique:users,employee_id'],
            'email'         => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'password'      => ['required', Password::min(6)],
            'role'          => ['required', Rule::in(['super_admin', 'procurement_admin', 'wh_admin', 'wh_manager', 'wh_supervisor', 'operator', 'user'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'     => ['boolean'],
        ];
    }
}
