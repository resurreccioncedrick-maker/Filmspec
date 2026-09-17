<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Mirrors requireRole() from core/auth.php in the legacy app.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role?->role_name, $roles, true)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
