<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Audit\FestEventActivityService;
use App\Support\TenancyDatabase;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
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
            'pendingSubmissionsCount'=> 0,
            'pendingPaymentsCount'   => \App\Models\MembershipPayment::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                                            ->where('status', 'submitted')->count(),
            'activeAcademicYear'     => \App\Support\AcademicYear::forSahodaya($this->sahodaya->id),
        ], $props));
    }

    /** @param  array<string, mixed>  $props */
    protected function withFestNavContext(array $props): array
    {
        $event = $props['event'] ?? null;
        if ((! is_array($event) || empty($event['id'])) && request()->filled('event_id')) {
            $festEvent = FestEvent::forTenant($this->sahodaya->id)
                ->whereKey(request('event_id'))
                ->first(['id', 'title', 'event_type', 'status', 'level_round']);
            if ($festEvent) {
                $props['event'] = $festEvent->only(['id', 'title', 'event_type', 'status', 'level_round']);
                $event = $props['event'];
            }
        }

        if (! is_array($event) || empty($event['id'])) {
            return $props;
        }

        if (! isset($props['programEvents'])) {
            $eventType = $event['event_type'] ?? FestEvent::query()->whereKey($event['id'])->value('event_type');
            if ($eventType) {
                $props['programEvents'] = FestEvent::forTenant($this->sahodaya->id)
                    ->ofType($eventType)
                    ->orderByDesc('event_start')
                    ->get(['id', 'title', 'status', 'event_start'])
                    ->all();
            }
        }

        if (! isset($props['program']) && ! empty($event['event_type'])) {
            $slugMap = [
                'kalolsavam'   => ['slug' => 'kalotsav', 'label' => 'Kalotsav', 'icon' => 'star'],
                'sports'       => ['slug' => 'sports-meet', 'label' => 'Sports Meet', 'icon' => 'award'],
                'kids_fest'    => ['slug' => 'kids-fest', 'label' => 'Kids Fest', 'icon' => 'users'],
                'teacher_fest' => ['slug' => 'teacher-fest', 'label' => 'Teacher Fest', 'icon' => 'users'],
                'custom'       => ['slug' => 'custom', 'label' => 'Custom Events', 'icon' => 'layers'],
            ];
            if (isset($slugMap[$event['event_type']])) {
                $props['program'] = array_merge($slugMap[$event['event_type']], ['eventType' => $event['event_type']]);
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
