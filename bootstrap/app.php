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
        then: function () {
            // Additive dedicated-domain group — see routes/state.php for why this is
            // safe to register unconditionally (inert until STATE_APP_DOMAIN is set).
            require __DIR__.'/../routes/state.php';
        },
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
            'state.judge.portal' => \App\Http\Middleware\EnsureStateJudgePortal::class,
            'state.domain'      => \App\Http\Middleware\EnsureStateDomainContext::class,
            'fest.event.ops'    => \App\Http\Middleware\EnsureFestEventOps::class,
            'fest.discipline'   => \App\Http\Middleware\EnsureFestDisciplineAdmin::class,
            'group.admin'       => \App\Http\Middleware\EnsureGroupAdmin::class,
            'house.admin'       => \App\Http\Middleware\EnsureHouseAdmin::class,
            'school.admin'      => \App\Http\Middleware\EnsureSchoolAdmin::class,
            'sahodaya.admin'    => \App\Http\Middleware\EnsureSahodayaAdmin::class,
            'region.report.scope' => \App\Http\Middleware\ResolveRegionScopedReportEvent::class,
            'school.admin.api'  => \App\Http\Middleware\EnsureSchoolAdminApi::class,
            'sahodaya.admin.api'=> \App\Http\Middleware\EnsureSahodayaAdminApi::class,
            'student.portal'    => \App\Http\Middleware\EnsureStudentPortal::class,
            'teacher.portal'    => \App\Http\Middleware\EnsureTeacherPortal::class,
            'judge.portal'      => \App\Http\Middleware\EnsureJudgePortal::class,
            'exam.portal'       => \App\Http\Middleware\EnsureExamPortal::class,
            'external.school.portal' => \App\Http\Middleware\EnsureExternalSchoolPortalAuth::class,
            'fest.mark.coordinator' => \App\Http\Middleware\EnsureFestMarkCoordinator::class,
            'password.change'       => \App\Http\Middleware\EnsurePasswordChanged::class,
            'event.coordinator'     => \App\Http\Middleware\EventCoordinatorScope::class,
            'public.cache'    => \App\Http\Middleware\SetPublicCacheHeaders::class,
            'website.enabled' => \App\Http\Middleware\EnsureWebsiteEnabled::class,
            'feature' => \App\Http\Middleware\EnsureTenantFeatureEnabled::class,
            'public.website.enabled' => \App\Http\Middleware\EnsureTenantPublicWebsiteEnabled::class,
            'public.website.admin.cms' => \App\Http\Middleware\EnsureTenantPublicWebsiteForAdminCms::class,
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

        // Business-rule rejections thrown via abort()/abort_if() with a human-readable message
        // (e.g. "State catalog items cannot be edited here.") used to fall through to Laravel's
        // raw exception response, which Inertia then displayed as a full error page in place of
        // the app — reported by users as a "popup error". This redirects back with the message
        // flashed instead, so it renders through the existing FlashBanner component (already
        // wired into every layout) as an inline alert, everywhere in the app, automatically —
        // covers every current and future abort/abort_if(..., 4xx, 'message') call site with no
        // per-controller changes needed.
        //
        // Deliberately narrow: only Inertia requests (API/JSON callers are untouched); only 4xx
        // client-error statuses; only when the exception actually carries a message (a bare
        // abort_if($cond, 403) with no text is a hard authorization/ownership guard for
        // broken or unauthorized state, not a user-facing message, and keeps rendering as a
        // normal error response); 401 and 419 are excluded since they already have dedicated
        // session/auth handling above. Also excludes a failed implicit route-model-binding
        // (Laravel converts ModelNotFoundException to a 404 NotFoundHttpException but keeps its
        // technical message, e.g. "No query results for model [App\Models\FestEventItem] 483" —
        // that's not meant for a user-facing alert banner, so it's left as a normal 404).
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return null;
            }

            $status = $e->getStatusCode();
            if ($status < 400 || $status >= 500 || $status === 401 || $status === 419) {
                return null;
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                && $e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return null;
            }

            $message = trim((string) $e->getMessage());
            if ($message === '') {
                return null;
            }

            return back()->withInput()->with('error', $message);
        });
    })->create();
