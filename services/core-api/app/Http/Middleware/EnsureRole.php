<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route group to one or more roles: `->middleware('role:vendor')` or `role:admin,vendor`.
 * Ownership (vendor A vs vendor B) is enforced separately in policies, not here.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthorizationException;
        }

        $userRole = $user->role instanceof Role ? $user->role->value : (string) $user->role;

        if (! in_array($userRole, $roles, true)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
