<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only if the logged-in user has one of the listed roles.
 *
 *   Route::post('/simulator/start', ...)->middleware('role:admin,manager');
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403, 'You do not have permission to do this.');
        }

        return $next($request);
    }
}
