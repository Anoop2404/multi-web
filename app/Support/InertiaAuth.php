<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class InertiaAuth
{
    public static function redirectToLogin(Request $request, ?string $message = null): Response
    {
        if ($message) {
            session()->flash('error', $message);
        }

        $url = route('login').'?session=expired';

        if ($request->header('X-Inertia')) {
            session()->put('url.intended', $request->fullUrl());

            return Inertia::location($url);
        }

        return redirect()->guest($url);
    }

    public static function intended(Request $request, string $default): RedirectResponse
    {
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && (self::isLoginUrl($intended) || self::isInvalidIntendedForUser($request, $intended))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($default);
    }

    public static function redirectTo(Request $request, string $url): Response|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            if (self::isSameOrigin($request, $url)) {
                return response('', 409, [Header::REDIRECT => self::normalizeRedirectUrl($url)]);
            }

            return Inertia::location($url);
        }

        return redirect()->to($url);
    }

    private static function isLoginUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return in_array($path, ['/login', '/school-login', '/portal/login'], true);
    }

    private static function isInvalidIntendedForUser(Request $request, string $url): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        if (method_exists($user, 'isSuperAdmin') && ! $user->isSuperAdmin() && self::requiresSuperAdmin($path)) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['school_admin', 'school_staff', 'school_principal', 'school_vice_principal'])) {
            if ($path === '/' || str_starts_with($path, '/sahodaya-admin/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A stale `url.intended` pointing at a route now (or still) behind 'super.admin'
     * must never be honored for a non-superadmin — otherwise a login just bounces
     * them straight into EnsureSuperAdmin's 403 with no way to reach their own
     * dashboard. Matched against the live route table instead of a hardcoded path
     * list, since that list silently fell behind as super.admin-gated prefixes
     * (builder, master-data, skin-presets, billing) were added later.
     */
    private static function requiresSuperAdmin(string $path): bool
    {
        try {
            $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return false;
        }

        return in_array('super.admin', $route->gatherMiddleware(), true);
    }

    private static function isSameOrigin(Request $request, string $url): bool
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return true;
        }

        return parse_url($url, PHP_URL_HOST) === $request->getHost();
    }

    private static function normalizeRedirectUrl(string $url): string
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
