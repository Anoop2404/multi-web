<?php

namespace App\Support;

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

    public static function intended(Request $request, string $default): Response
    {
        $target = redirect()->intended($default)->getTargetUrl();

        if (self::isLoginUrl($target)) {
            $target = $default;
        }

        return self::redirectTo($request, $target);
    }

    public static function redirectTo(Request $request, string $url): Response
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

        return $path === '/login';
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
