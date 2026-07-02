<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

class ApiDocsController extends Controller
{
    public function index()
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'))
            ->map(fn ($route) => [
                'methods'     => array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS'])),
                'uri'         => '/'.$route->uri(),
                'name'        => $route->getName(),
                'middleware'  => collect($route->middleware())->values()->all(),
            ])
            ->sortBy('uri')
            ->values();

        return response()->json([
            'version'     => 'v1',
            'generated_at'=> now()->toIso8601String(),
            'auth'        => 'Bearer token via POST /api/v1/auth/login',
            'routes'      => $routes,
        ]);
    }
}
