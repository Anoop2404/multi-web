<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use App\Support\InertiaAuth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // On central /sahodaya-admin/{tenantId} routes, tenancy must start AFTER the session
        // (so database/file sessions stay on the central store) but BEFORE route model binding.
        $middleware->prependToPriorityList(
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\InitializeTenancyByRouteTenant::class,
        );
        $middleware->prependToPriorityList(
            \App\Http\Middleware\InitializeTenancyByRouteTenant::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        );

        $middleware->web(prepend: [
            \App\Http\Middleware\ResolveAuthenticationGuard::class,
            \App\Http\Middleware\InitializeTenancyByRouteTenant::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\RefreshAuthenticatedUser::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ResolveAuthenticationGuard::class,
            \App\Http\Middleware\InitializeTenancyByRouteTenant::class,
        ]);

        $middleware->alias([
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'super.admin'       => \App\Http\Middleware\EnsureSuperAdmin::class,
            'state.admin'       => \App\Http\Middleware\EnsureStateAdmin::class,
            'fest.event.ops'    => \App\Http\Middleware\EnsureFestEventOps::class,
            'fest.discipline'   => \App\Http\Middleware\EnsureFestDisciplineAdmin::class,
            'group.admin'       => \App\Http\Middleware\EnsureGroupAdmin::class,
            'house.admin'       => \App\Http\Middleware\EnsureHouseAdmin::class,
            'school.admin'      => \App\Http\Middleware\EnsureSchoolAdmin::class,
            'sahodaya.admin'    => \App\Http\Middleware\EnsureSahodayaAdmin::class,
            'school.admin.api'  => \App\Http\Middleware\EnsureSchoolAdminApi::class,
            'sahodaya.admin.api'=> \App\Http\Middleware\EnsureSahodayaAdminApi::class,
            'student.portal'    => \App\Http\Middleware\EnsureStudentPortal::class,
            'teacher.portal'    => \App\Http\Middleware\EnsureTeacherPortal::class,
            'judge.portal'      => \App\Http\Middleware\EnsureJudgePortal::class,
            'exam.portal'       => \App\Http\Middleware\EnsureExamPortal::class,
            'fest.mark.coordinator' => \App\Http\Middleware\EnsureFestMarkCoordinator::class,
            'password.change'       => \App\Http\Middleware\EnsurePasswordChanged::class,
            'event.coordinator'     => \App\Http\Middleware\EventCoordinatorScope::class,
            'public.cache'    => \App\Http\Middleware\SetPublicCacheHeaders::class,
            'website.enabled' => \App\Http\Middleware\EnsureWebsiteEnabled::class,
            'public.website.enabled' => \App\Http\Middleware\EnsureTenantPublicWebsiteEnabled::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login').'?session=expired');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $sessionExpiredMessage = 'Your session has expired. Please sign in again.';

        $inertiaSessionExpired = function (Request $request) use ($sessionExpiredMessage) {
            if ($request->header('X-Inertia')) {
                return InertiaAuth::redirectToLogin($request, $sessionExpiredMessage);
            }

            return null;
        };

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($inertiaSessionExpired) {
            return $inertiaSessionExpired($request);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($inertiaSessionExpired) {
            if ($e->getStatusCode() === 419) {
                return $inertiaSessionExpired($request);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($inertiaSessionExpired) {
            if ($request->header('X-Inertia')) {
                return $inertiaSessionExpired($request);
            }

            return null;
        });
    })->create();
