<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Tests\TestCase;

class InertiaAssetVersionTest extends TestCase
{
    public function test_new_build_changes_version_even_with_a_fixed_asset_url(): void
    {
        $original = public_path();
        $temporary = sys_get_temp_dir().'/inertia-version-'.bin2hex(random_bytes(8));
        mkdir($temporary.'/build', 0700, true);
        $this->app->usePublicPath($temporary);
        config(['app.asset_url' => 'https://assets.example.test']);

        try {
            $middleware = new HandleInertiaRequests;
            $request = Request::create('/');
            file_put_contents($temporary.'/build/manifest.json', '{"file":"old.js"}');
            $old = $middleware->version($request);
            $this->assertSame($old, $middleware->version($request));
            file_put_contents($temporary.'/build/manifest.json', '{"file":"new.js"}');
            $this->assertNotSame($old, $middleware->version($request));
        } finally {
            $this->app->usePublicPath($original);
            unlink($temporary.'/build/manifest.json');
            rmdir($temporary.'/build');
            rmdir($temporary);
        }
    }

    public function test_stale_frontend_requests_trigger_a_full_reload_and_are_not_cached(): void
    {
        $middleware = new class extends HandleInertiaRequests {
            public function share(Request $request): array { return []; }
        };
        $request = Request::create('/schedule/chest-numbers', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'previous-deployment');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $middleware->handle($request, fn () => Inertia::render('Test')->toResponse($request));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame($request->fullUrl(), $response->headers->get('X-Inertia-Location'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function test_portal_html_cannot_be_stored_by_browser_or_shared_caches(): void
    {
        $middleware = new class extends HandleInertiaRequests {
            public function share(Request $request): array { return []; }
        };
        $response = $middleware->handle(Request::create('/portal'), fn () => response('portal html'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }
}
