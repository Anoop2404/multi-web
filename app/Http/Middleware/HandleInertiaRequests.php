<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'admin';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? array_merge(
                    $request->user()->only('id', 'name', 'email', 'email_verified_at'),
                    ['roles' => $request->user()->getRoleNames()->values()->all()]
                ) : null,
            ],
            'features' => [
                'website_enabled' => \App\Support\FeatureFlags::websiteEnabled(),
            ],
            'flash' => [
                'success'      => fn () => $request->session()->get('success'),
                'error'        => fn () => $request->session()->get('error'),
                'warning'      => fn () => $request->session()->get('warning'),
                'info'         => fn () => $request->session()->get('info'),
                'importResult' => fn () => $request->session()->get('importResult'),
                'import_errors' => fn () => $request->session()->get('import_errors'),
                'newCredentials' => fn () => $request->session()->get('newCredentials'),
                'mcqNewCredentials' => fn () => $request->session()->get('mcqNewCredentials'),
                'studentPortalCredentials' => fn () => $request->session()->get('studentPortalCredentials'),
            ],
            'old' => fn () => $request->old(),
        ];
    }
}
