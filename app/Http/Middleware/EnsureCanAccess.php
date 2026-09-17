<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccess
{
    /**
     * Mirrors requireAccess()/canAccess() from core/auth.php in the legacy app.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        $role = $user->role?->role_name;
        $permissions = config("filmspec.role_permissions.$role", []);

        if (! $user || ! in_array($module, $permissions, true)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
