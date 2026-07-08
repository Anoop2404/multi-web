<?php

namespace App\Http\Middleware;

use App\Models\PlatformUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\CacheManager as TenantCacheManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the session auth user in sync when verification or account fields change in the DB
 * (e.g. user verifies email in another tab, or Sahodaya approves while they stay logged in).
 */
class RefreshAuthenticatedUser
{
    private const CACHE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User || $user instanceof PlatformUser) {
            $cacheKey = 'auth_refresh:'.sha1($user::class.':'.$user->getAuthIdentifier());

            $cache = $this->untaggedCache();

            if (! $cache->get($cacheKey)) {
                $model = $user instanceof PlatformUser ? PlatformUser::class : User::class;
                $fresh = $model::query()->find($user->getAuthIdentifier());

                if ($fresh && $this->shouldRefresh($user, $fresh)) {
                    Auth::login($fresh, Auth::viaRemember());
                }

                $cache->put($cacheKey, true, self::CACHE_SECONDS);
            }
        }

        return $next($request);
    }

    private function shouldRefresh(User|PlatformUser $sessionUser, User|PlatformUser $freshUser): bool
    {
        return $sessionUser->email_verified_at?->getTimestamp() !== $freshUser->email_verified_at?->getTimestamp()
            || $sessionUser->email !== $freshUser->email
            || $sessionUser->tenant_id !== $freshUser->tenant_id;
    }

    /**
     * Stancl tenancy wraps Cache facade calls with tenant tags; file/array drivers cannot tag.
     */
    private function untaggedCache(): CacheRepository
    {
        $manager = app('cache');

        return $manager instanceof TenantCacheManager
            ? $manager->store()
            : Cache::store();
    }
}
