<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $allowed = ['name', 'email', 'role'];
        $sort    = $request->sort && in_array($request->sort, $allowed) ? $request->sort : null;
        $dir     = $request->dir === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->with(['department:id,name'])
            ->when($request->search, function ($q, $search) {
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                );
            })
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status === 'active')
            )
            ->when($request->filled('department_id'), fn ($q) =>
                $q->where('department_id', $request->department_id)
            )
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->latest())
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'employee_id'   => $u->employee_id,
                'email'         => $u->email,
                'role'          => $u->role,
                'extra_roles'   => $u->extra_roles ?? [],
                'department_id' => $u->department_id,
                'is_active'     => $u->is_active,
                'last_login_at' => $u->last_login_at?->diffForHumans(),
                'department'    => $u->department?->name,
            ]);

        return Inertia::render('Master/Users/Index', [
            'users'       => $users,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only(['search', 'role', 'status', 'department_id', 'sort', 'dir']),
            'stats'       => [
                'total'       => User::count(),
                'active'      => User::where('is_active', true)->count(),
                'inactive'    => User::where('is_active', false)->count(),
                'departments' => Department::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return back()->with('success', 'User created successfully.');
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        if ($user->role === 'super_admin') {
            return back()->withErrors(['update' => 'Super admin account cannot be modified.']);
        }

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->role === 'super_admin') {
            return back()->withErrors(['delete' => 'Super admin account cannot be deleted.']);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'Cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    public function impersonate(User $user): RedirectResponse
    {
        // Only super_admin can impersonate
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }
        // Cannot impersonate super_admin
        if ($user->role === 'super_admin') {
            return back()->with('error', 'Cannot impersonate super admin account.');
        }
        // Don't impersonate yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot impersonate yourself.');
        }
        // Store original user id in session
        session(['impersonating_from' => auth()->id()]);
        auth()->login($user);
        return redirect()->route('dashboard')->with('success', 'Now viewing as ' . $user->name);
    }

    public function stopImpersonate(): RedirectResponse
    {
        $originalId = session('impersonating_from');
        if (!$originalId) {
            return redirect()->route('dashboard');
        }
        $originalUser = User::find($originalId);
        if (!$originalUser) {
            return redirect()->route('dashboard');
        }
        session()->forget('impersonating_from');
        auth()->login($originalUser);
        return redirect()->route('users.index')->with('success', 'Returned to your account.');
    }
}
