<?php

namespace App\Http\Middleware;

use App\Support\StateFestPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 of the State Kalotsav module — per-capability authorisation for State screens.
 *
 * EnsureStateAdmin answers "is this a state user, and which state are they scoped to". It cannot
 * answer "may this user publish results", because until now the only distinction available was
 * state_staff being refused every non-GET request. That is too coarse for the module: a mark
 * operator must be able to write marks but never publish, and a certificate operator must be able
 * to generate certificates but never touch a result.
 *
 * Used as `state.fest:marks` (the `state.fest.` prefix is implied), and stacks after state.admin,
 * which has already established the user is a state user and stashed their state on the request.
 */
class EnsureStateFestPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Superadmin bypasses the matrix, consistently with EnsureStateAdmin and StateScope.
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        $required = array_map(
            fn (string $p) => str_starts_with($p, 'state.fest.') ? $p : "state.fest.{$p}",
            $permissions,
        );

        foreach ($required as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        $labels = StateFestPermissions::labels();
        $needed = collect($required)->map(fn ($p) => $labels[$p] ?? $p)->implode(' or ');

        // Names the missing capability rather than saying "forbidden": these roles exist precisely
        // so an operator can be told what they are not allowed to do.
        abort(403, "Your State role does not allow this: {$needed}.");
    }
}
