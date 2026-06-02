<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Check DB role_permissions for the current user.
     *
     * Usage in routes: middleware('permission:moduleName')
     * - GET/HEAD  → requires can_view
     * - POST      → requires can_create OR can_edit (POST is used for both)
     * - PUT/PATCH → requires can_edit
     * - DELETE    → requires can_delete
     *
     * super_admin always bypasses all checks.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            return $this->deny($request);
        }

        // super_admin bypasses everything
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        $perm = RolePermission::where('role', $user->role)
            ->where('module', $module)
            ->first();

        if (!$perm) {
            return $this->deny($request);
        }

        $allowed = match (strtoupper($request->method())) {
            'GET', 'HEAD'  => (bool) $perm->can_view,
            'POST'         => (bool) ($perm->can_create || $perm->can_edit || $perm->can_approve),
            'PUT', 'PATCH' => (bool) $perm->can_edit,
            'DELETE'       => (bool) $perm->can_delete,
            default        => (bool) $perm->can_view,
        };

        return $allowed ? $next($request) : $this->deny($request);
    }

    private function deny(Request $request): Response
    {
        if ($request->header('X-Inertia') || $request->expectsJson()) {
            abort(403, 'Access denied.');
        }
        return redirect()->route('dashboard')->with('error', 'Access denied.');
    }
}
