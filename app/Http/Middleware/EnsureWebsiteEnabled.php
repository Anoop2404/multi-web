<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebsiteEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! FeatureFlags::websiteEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
