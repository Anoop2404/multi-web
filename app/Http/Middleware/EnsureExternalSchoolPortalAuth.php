<?php

namespace App\Http\Middleware;

use App\Models\ExternalSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * External-school "login" is a session flag, not a Laravel Auth guard. These routes run on
 * the central host, where ResolveAuthenticationGuard force-sets the `platform` guard ahead of
 * routing — a third guard would need every touchpoint to remember to say
 * Auth::guard('external_school') and never $request->user(), which is an easy mistake to make.
 * A bespoke session key sidesteps that entirely.
 */
class EnsureExternalSchoolPortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $schoolId = $request->session()->get('external_school_id');
        $school = $schoolId ? ExternalSchool::find($schoolId) : null;

        if (! $school || ! $school->isActive() || ! $school->sahodaya?->isActive()) {
            $request->session()->forget('external_school_id');

            return redirect()->route('state.external.school.login');
        }

        $request->attributes->set('externalSchool', $school);

        return $next($request);
    }
}
