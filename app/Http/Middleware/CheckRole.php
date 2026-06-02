<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Allow if primary role matches, OR any extra_roles grant access
        $allowed = $user && $user->is_active && (
            in_array($user->role, $roles) ||
            !empty(array_intersect($roles, $user->extra_roles ?? []))
        );

        if (!$allowed) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                abort(403, 'Unauthorized.');
            }
            return redirect()->route('dashboard')->with('error', 'Access denied.');
        }
        return $next($request);
    }
}
