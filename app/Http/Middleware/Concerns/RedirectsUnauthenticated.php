<?php

namespace App\Http\Middleware\Concerns;

use App\Support\InertiaAuth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait RedirectsUnauthenticated
{
    protected function redirectToLogin(Request $request): Response
    {
        return InertiaAuth::redirectToLogin(
            $request,
            'Your session has expired. Please sign in again.',
        );
    }
}
