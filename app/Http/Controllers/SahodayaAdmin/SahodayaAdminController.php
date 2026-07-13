<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\FestEventActivityService;
use App\Services\Membership\SahodayaSetupService;
use App\Support\ProgramRouteMap;
use App\Models\SahodayaProfile;
use App\Support\SahodayaNavVisibility;
use App\Support\TenancyDatabase;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use App\Support\TenantPublicSite;
use Illuminate\Http\Request;

abstract class SahodayaAdminController extends Controller
{
    protected Tenant $sahodaya;
    protected bool $isStaff = false;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
        $this->isStaff = (bool) $request->attributes->get('isSahodayaStaff', false);

        if ($this->isStaff && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $permission = \App\Support\TenantUserCatalog::writePermissionForPath($request->path());
            if ($permission === null || ! $request->user()?->can($permission)) {
                abort(403, 'View-only access. Contact your Sahodaya administrator.');
            }
        }
    }

    protected function assertStaffCan(string $permission): void
    {
        if ($this->isStaff && ! request()->user()?->can($permission)) {
            abort(403, 'You do not have permission for this action.');
        }
    }

    protected function inertia(string $component, array $props = [])
    {
        $props = $this->withFestNavContext($props);

        $staffPermissions = null;
        if ($this->isStaff && ($user = request()->user())) {
            $staffPermissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return inertia($component, array_merge([
            'isStaff' => $this->isStaff,
            'staffPermissions' => $staffPermissions,
            'navVisibility' => SahodayaNavVisibility::forProfile(
                SahodayaProfile::where('tenant_id', $this->sahodaya->id)->first(),
                $this->sahodaya->nav_overrides,
            ),
            'sahodaya'               => array_merge(
                $this->sahodaya->only('id', 'name', 'type'),
                ['logo_url' => TenantBranding::logoUrl($this->sahodaya)]
            ),
            'publicUrl'              => TenantDomainSync::publicUrl($this->sahodaya),
            'approvedSchoolsCount'   => Tenant::where('parent_id', $this->sahodaya->id)
                                            ->where('type', 'school')
                                            ->where('membership_status', 'approved')
                                            ->count(),
            'pendingSchoolsCount'    => Tenant::where('parent_id', $this->sahodaya->id)
                                            ->where('type', 'school')
                                            ->where('membership_status', 'pending')
                                            ->count(),
            'pendingSubmissionsCount'=> Registration::query()
                ->whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                ->where('academic_year', \App\Support\AcademicYear::forSahodaya($this->sahodaya->id))
                ->whereIn('registration_status', ['data_pending', 'data_rejected'])
                ->count(),
            'pendingPaymentsCount'   => \App\Models\MembershipPayment::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                                            ->where('status', 'submitted')->count(),
            'pendingChangeRequests'  => \App\Models\StudentEditChangeRequest::query()
                ->forSahodaya($this->sahodaya->id)
                ->where('status', 'pending')
                ->whereIn('school_approval_status', ['school_approved', 'bypassed'])
                ->count(),
            'unverifiedStudentsCount' => Student::query()
                ->whereIn('tenant_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->count(),
            'pendingFestAppealsCount' => \App\Models\FestAppeal::query()
                ->whereIn('event_id', \App\Models\FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id'))
                ->where('status', 'pending')
                ->count(),
            'activeAcademicYear'     => \App\Support\AcademicYear::forSahodaya($this->sahodaya->id),
            'stateRemittancesEnabled' => \App\Models\FestStateProgramPropagation::where('sahodaya_id', $this->sahodaya->id)->exists(),
            'setupIncompleteCount'    => $this->isStaff ? 0 : collect(app(SahodayaSetupService::class)->checklist($this->sahodaya))
                ->where('done', false)->count(),
            'competitionPrograms'     => app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                ->forTenant($this->sahodaya->id)
                ->programsForNav(),
            'publicWebsiteEnabled'    => TenantPublicSite::isEnabled($this->sahodaya),
        ], $props));
    }

    /** Program hub / catalog paths should keep program sidebar — ignore ?event_id= there. */
    protected function isProgramWorkspaceRequest(): bool
    {
        $path = parse_url(request()->getRequestUri(), PHP_URL_PATH) ?? '';

        return (bool) preg_match(
            '#/sahodaya-admin/[^/]+/(?:kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)(?:/(?:catalog|age-groups|records|championship|results|rankings|school-rounds)(?:/|$)|(?:/|$)|$)#',
            $path,
        ) || str_contains($path, '/taxonomy-masters')
            || str_contains($path, '/competition-types')
            || (bool) preg_match('#/sahodaya-admin/[^/]+/programs/[^/]+#', $path);
    }

    /** @return array{program: array<string, mixed>, programEvents: list<\App\Models\FestEvent>} */
    protected function programNavProps(string $slug): array
    {
        $meta = app(\App\Services\Events\FestCompetitionTypeRegistry::class)
            ->forTenant($this->sahodaya->id)
            ->programMeta($slug);
        abort_unless($meta !== null, 404);

        $eventType = $meta['eventType'];

        return [
            'program' => [
                'slug'      => $meta['slug'],
                'eventType' => $eventType,
                'label'     => $meta['label'],
                'icon'      => $meta['icon'],
                'prefix'    => $meta['prefix'],
            ],
            'programEvents' => FestEvent::forTenant($this->sahodaya->id)
                ->ofType($eventType)
                ->visibleInNav()
                ->orderByDesc('event_start')
                ->get(['id', 'title', 'status'])
                ->all(),
        ];
    }

    /** @param  array<string, mixed>  $props */
    protected function withFestNavContext(array $props): array
    {
        $event = $props['event'] ?? null;
        $hasEvent = is_array($event) || $event instanceof FestEvent;
        $eventId = $hasEvent ? ($event['id'] ?? null) : null;

        if ((! $hasEvent || empty($eventId)) && request()->filled('event_id') && ! $this->isProgramWorkspaceRequest()) {
            $festEvent = FestEvent::forTenant($this->sahodaya->id)
                ->whereKey(request('event_id'))
                ->first(['id', 'title', 'event_type', 'status', 'level_round']);
            if ($festEvent) {
                $props['event'] = $festEvent->only(['id', 'title', 'event_type', 'status', 'level_round']);
                $event = $props['event'];
                $hasEvent = true;
                $eventId = $event['id'];
            }
        }

        if (! $hasEvent || empty($eventId)) {
            return $props;
        }

        $eventType = $event['event_type'] ?? null;

        if (! isset($props['programEvents'])) {
            $eventType ??= FestEvent::query()->whereKey($eventId)->value('event_type');
            if ($eventType) {
                $props['programEvents'] = FestEvent::forTenant($this->sahodaya->id)
                    ->ofType($eventType)
                    ->visibleInNav()
                    ->orderByDesc('event_start')
                    ->get(['id', 'title', 'status', 'event_start'])
                    ->all();
            }
        }

        if (! isset($props['program']) && ! empty($eventType)) {
            $slug = app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                ->forTenant($this->sahodaya->id)
                ->slugForEventType($eventType);
            $meta = $slug
                ? app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                    ->forTenant($this->sahodaya->id)
                    ->programMeta($slug)
                : null;
            if ($meta) {
                $props['program'] = [
                    'slug' => $meta['slug'],
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                    'eventType' => $eventType,
                    'prefix' => $meta['prefix'],
                ];
            }
        }

        if (! isset($props['eventHeadNav'])) {
            $festEvent = $event instanceof FestEvent
                ? $event
                : FestEvent::query()->whereKey($eventId)->where('tenant_id', $this->sahodaya->id)->first();
            if ($festEvent) {
                $nav = app(\App\Services\Events\FestHeadItemNavigationService::class);
                $props['eventHeadNav'] = $nav->slimNavigation($nav->navigationForEvent($festEvent));
            }
        }

        return $props;
    }

    /** @param  array<string, mixed>  $props */
    protected function withEventActivity(FestEvent $event, string $page, array $props = [], int $limit = 20): array
    {
        return array_merge($props, [
            'activityLogs' => $this->pageActivityLogs($event, $page, $limit),
        ]);
    }

    /** @return list<array<string, mixed>> */
    protected function pageActivityLogs(FestEvent $event, string $page, int $limit = 20): array
    {
        return app(FestEventActivityService::class)
            ->forPage($event, $page, $limit)
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function catalogActivityLogs(string $page, int $limit = 20): array
    {
        return app(FestEventActivityService::class)
            ->forCatalog($this->sahodaya->id, $page, $limit);
    }

    /** @return list<array<string, mixed>> */
    protected function programActivityLogs(string $program, int $limit = 20): array
    {
        return app(FestEventActivityService::class)
            ->forProgram($this->sahodaya->id, $program, $limit);
    }
}
