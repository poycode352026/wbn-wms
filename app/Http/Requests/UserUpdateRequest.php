<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'          => ['required', 'string', 'max:100'],
            'employee_id'   => ['required', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($userId)],
            'email'         => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($userId)],
            'password'      => ['nullable', 'string', Password::min(6)],
            'role'          => ['required', Rule::in(['super_admin', 'procurement_admin', 'wh_admin', 'admin_dept', 'manager_dept', 'wh_manager', 'wh_supervisor', 'operator', 'employee', 'user'])],
            'extra_roles'   => ['nullable', 'array'],
            'extra_roles.*' => [Rule::in(['operator', 'wh_admin', 'wh_supervisor'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'     => ['boolean'],
        ];
    }
}
