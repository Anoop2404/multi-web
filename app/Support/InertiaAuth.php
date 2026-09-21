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

        if (method_exists($user, 'isSuperAdmin') && ! $user->isSuperAdmin()) {
            if (in_array($path, ['/dashboard', '/schools', '/tenants', '/states', '/announcements'], true)
                || str_starts_with($path, '/schools/')
                || str_starts_with($path, '/tenants/')
                || str_starts_with($path, '/states/')
                || str_starts_with($path, '/announcements/')) {
                return true;
            }
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['school_admin', 'school_staff', 'school_principal', 'school_vice_principal'])) {
            if ($path === '/' || str_starts_with($path, '/sahodaya-admin/')) {
                return true;
            }
        }

        return false;
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
