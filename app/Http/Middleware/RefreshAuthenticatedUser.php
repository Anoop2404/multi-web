<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the session auth user in sync when verification or account fields change in the DB
 * (e.g. user verifies email in another tab, or Sahodaya approves while they stay logged in).
 */
class RefreshAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $fresh = User::query()->find($user->getAuthIdentifier());

            if ($fresh && $this->shouldRefresh($user, $fresh)) {
                Auth::login($fresh, Auth::viaRemember());
            }
        }

        return $next($request);
    }

    private function shouldRefresh(User $sessionUser, User $freshUser): bool
    {
        return $sessionUser->email_verified_at?->getTimestamp() !== $freshUser->email_verified_at?->getTimestamp()
            || $sessionUser->email !== $freshUser->email
            || $sessionUser->tenant_id !== $freshUser->tenant_id;
    }
}
