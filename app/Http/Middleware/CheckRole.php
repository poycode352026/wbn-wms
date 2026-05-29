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
        if (!$user || !$user->is_active || !in_array($user->role, $roles)) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                abort(403, 'Unauthorized.');
            }
            return redirect()->route('dashboard')->with('error', 'Access denied.');
        }
        return $next($request);
    }
}
