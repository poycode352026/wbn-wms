<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    private const ROLES = [
        'super_admin', 'admin_dept', 'manager_dept',
        'warehouse_manager', 'supervisor', 'operator',
    ];

    private const MODULES = [
        'users', 'departments', 'permissions',
        'warehouses', 'rackMgmt', 'locations',
        'itemMaster', 'goodsReceipt', 'goodsIssue',
        'inventoryReport', 'transactionLog', 'auditTrail',
    ];

    public function index(): Response
    {
        $roleCounts = User::where('is_active', true)
            ->selectRaw('role, count(*) as cnt')
            ->groupBy('role')
            ->pluck('cnt', 'role')
            ->all();

        // Load all DB permissions, grouped by role → module → [v,c,e,d,a]
        $dbRows = RolePermission::all();
        $rolePermissions = [];

        foreach (self::ROLES as $role) {
            $rolePermissions[$role] = [];
        }

        foreach ($dbRows as $row) {
            if (!in_array($row->role, self::ROLES)) continue;
            $rolePermissions[$row->role][$row->module] = [
                (int) $row->can_view,
                (int) $row->can_create,
                (int) $row->can_edit,
                (int) $row->can_delete,
                $row->can_approve, // null | 0 | 1
            ];
        }

        return Inertia::render('Master/Permissions/Index', [
            'roleCounts'      => $roleCounts,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    public function save(Request $request, string $role): RedirectResponse
    {
        // super_admin permissions are fixed — cannot be overwritten
        if ($role === 'super_admin') {
            return back()->withErrors(['save' => 'Super admin permissions cannot be modified.']);
        }

        if (!in_array($role, self::ROLES)) {
            abort(422, 'Invalid role.');
        }

        $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['array'],
        ]);

        foreach ($request->permissions as $module => $perms) {
            if (!in_array($module, self::MODULES)) continue;

            RolePermission::updateOrCreate(
                ['role' => $role, 'module' => $module],
                [
                    'can_view'    => (bool) ($perms[0] ?? false),
                    'can_create'  => (bool) ($perms[1] ?? false),
                    'can_edit'    => (bool) ($perms[2] ?? false),
                    'can_delete'  => (bool) ($perms[3] ?? false),
                    'can_approve' => isset($perms[4]) ? ($perms[4] === null ? null : (int) $perms[4]) : null,
                ]
            );
        }

        return back()->with('success', 'Permissions saved successfully.');
    }
}
