<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentStoreRequest;
use App\Http\Requests\DepartmentUpdateRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['code', 'name'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $departments = Department::query()
            ->withCount([
                'users',
                'employees as employees_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->with('admins')
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%{$s}%")
                       ->orWhere('code', 'like', "%{$s}%")
                ))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderBy('name'))
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($d) => [
                'id'              => $d->id,
                'code'            => $d->code,
                'name'            => $d->name,
                'users_count'     => $d->users_count,
                'employees_count' => $d->employees_count,
                'is_active'       => $d->is_active,
                'admins'          => $d->admins->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role])->values(),
            ]);

        return Inertia::render('Master/Departments/Index', [
            'departments' => $departments,
            'filters'     => $request->only(['search', 'status', 'sort', 'dir']),
            'stats'       => [
                'total'    => Department::count(),
                'active'   => Department::where('is_active', true)->count(),
                'inactive' => Department::where('is_active', false)->count(),
                'users'    => User::count(),
            ],
            'allUsers' => User::where('is_active', true)
                ->where('role', 'user')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'department_id', 'role']),
        ]);
    }

    public function store(DepartmentStoreRequest $request): RedirectResponse
    {
        Department::create($request->validated());
        return back()->with('success', 'Department created successfully.');
    }

    public function update(DepartmentUpdateRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());
        return back()->with('success', 'Department updated successfully.');
    }

    public function assignAdmin(Request $request, Department $department): RedirectResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['required', 'in:admin_dept,manager_dept'],
        ]);
        User::findOrFail($request->user_id)->update([
            'department_id' => $department->id,
            'role'          => $request->role,
        ]);
        return back()->with('success', 'Member assigned successfully.');
    }

    public function removeAdmin(Request $request, Department $department): RedirectResponse
    {
        $request->validate(['user_id' => ['required', 'exists:users,id']]);
        User::where('id', $request->user_id)
            ->where('department_id', $department->id)
            ->update(['department_id' => null, 'role' => 'user']);
        return back()->with('success', 'Member removed from department.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete: department has users assigned.']);
        }
        $department->delete();
        return back()->with('success', 'Department deleted successfully.');
    }
}