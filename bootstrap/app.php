<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant DB must be active before route model binding resolves tenant-scoped models.
        $middleware->prependToPriorityList(
            \App\Http\Middleware\InitializeTenancyByRouteTenant::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        );

        $middleware->web(prepend: [
            \App\Http\Middleware\InitializeTenancyByRouteTenant::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role'            => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'      => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'school.admin'    => \App\Http\Middleware\EnsureSchoolAdmin::class,
            'sahodaya.admin'  => \App\Http\Middleware\EnsureSahodayaAdmin::class,
            'public.cache'    => \App\Http\Middleware\SetPublicCacheHeaders::class,
            'website.enabled' => \App\Http\Middleware\EnsureWebsiteEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
