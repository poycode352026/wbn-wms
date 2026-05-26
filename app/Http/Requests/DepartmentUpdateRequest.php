<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin']);
    }

    public function rules(): array
    {
        $deptId = $this->route('department')?->id;

        return [
            'code'      => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($deptId)],
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}