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
                    ['roles' => $this->roleNames($request)]
                ) : null,
            ],
            'impersonating' => fn () => $this->impersonating($request),
            'announcements' => fn () => $this->activeAnnouncements($request),
            'flash' => [
                'success'      => fn () => $request->session()->get('success'),
                'error'        => fn () => $request->session()->get('error'),
                'warning'      => fn () => $request->session()->get('warning'),
                'info'         => fn () => $request->session()->get('info'),
                'importResult' => fn () => $request->session()->get('importResult'),
                'bulkMarkSaveResult' => fn () => $request->session()->get('bulkMarkSaveResult'),
                'import_errors' => fn () => $request->session()->get('import_errors'),
                'newCredentials' => fn () => $request->session()->get('newCredentials'),
                'mcqNewCredentials' => fn () => $request->session()->get('mcqNewCredentials'),
                'studentPortalCredentials' => fn () => $request->session()->get('studentPortalCredentials'),
            ],
            'old' => fn () => $request->old(),
        ];
    }

    /** @return array{sessionId: int}|null */
    private function impersonating(Request $request): ?array
    {
        $sessionId = $request->session()->get('impersonation_session_id');

        return $sessionId ? ['sessionId' => $sessionId] : null;
    }

    /** @return list<string> */
    private function roleNames(Request $request): array
    {
        $user = $request->user();

        return $user->tenant_id === null
            ? \Illuminate\Support\Facades\DB::connection(config('tenancy.database.central_connection', 'central'))
                ->table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->whereIn('model_has_roles.model_type', [\App\Models\User::class, \App\Models\PlatformUser::class])
                ->pluck('roles.name')
                ->unique()
                ->values()
                ->all()
            : $user->getRoleNames()->values()->all();
    }

    /** @return list<array{id: int, title: string, body: string, type: string}> */
    private function activeAnnouncements(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        $audiences = array_values(array_intersect(
            $this->roleNames($request),
            ['superadmin', 'state_admin', 'sahodaya_admin', 'school_admin']
        ));

        return \App\Models\PlatformAnnouncement::currentlyActive()
            ->forAudience($audiences)
            ->orderByDesc('id')
            ->get(['id', 'title', 'body', 'type'])
            ->all();
    }
}
