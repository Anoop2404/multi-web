<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
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
            return Inertia::location($url);
        }

        return redirect()->guest($url);
    }

    public static function redirectTo(Request $request, string $url): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }
}
