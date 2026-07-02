<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SchoolHouse;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\SchoolHouseFestPointsService;
use Illuminate\Http\Request;

class HouseAdminController extends Controller
{
    public function index(Request $request, string $tenantId, SchoolHouseFestPointsService $pointsService)
    {
        $user = $request->user();
        $school = Tenant::findOrFail($tenantId);
        $house = $this->resolveHouse($user, $tenantId);

        $studentIds = Student::where('tenant_id', $tenantId)
            ->when($house, fn ($q) => $q->where('school_house_id', $house->id))
            ->active()
            ->pluck('id');

        $registrations = FestRegistration::whereIn('status', ['submitted', 'approved'])
            ->where('school_id', $tenantId)
            ->whereHas('participants', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->with(['event', 'item', 'participants.student'])
            ->latest()
            ->limit(50)
            ->get();

        $houseRanking = $pointsService->rankingForSchool($tenantId);
        $myRank = $house
            ? collect($houseRanking)->firstWhere('house_id', $house->id)
            : null;

        return inertia('Portal/HouseAdmin/Dashboard', [
            'school'         => $school->only('id', 'name', 'school_prefix'),
            'house'          => $house?->only('id', 'name', 'color', 'motto'),
            'studentCount'   => $studentIds->count(),
            'registrations'  => $registrations,
            'houseRanking'   => $houseRanking,
            'myHouseStats'   => $myRank,
            'events'         => $school->parent_id
                ? $pointsService->openEventsForSchool($tenantId, $school->parent_id)
                : [],
            'user'           => $user->only('id', 'name', 'email'),
        ]);
    }

    public function students(Request $request, string $tenantId)
    {
        $user = $request->user();
        $house = $this->resolveHouse($user, $tenantId);

        $students = Student::where('tenant_id', $tenantId)
            ->when($house, fn ($q) => $q->where('school_house_id', $house->id))
            ->active()
            ->with('schoolClass.classCategory')
            ->orderBy('school_class_id')
            ->orderBy('name')
            ->get(['id', 'name', 'reg_no', 'school_class_id', 'roll_number', 'gender', 'school_house_id']);

        $festCounts = FestParticipant::whereIn('student_id', $students->pluck('id'))
            ->whereHas('registration', fn ($q) => $q->where('school_id', $tenantId)->whereIn('status', ['submitted', 'approved']))
            ->selectRaw('student_id, count(*) as cnt')
            ->groupBy('student_id')
            ->pluck('cnt', 'student_id');

        return inertia('Portal/HouseAdmin/Students', [
            'tenantId'   => $tenantId,
            'house'      => $house?->only('id', 'name', 'color'),
            'students'   => $students->map(fn ($s) => [
                ...$s->toArray(),
                'fest_entries' => $festCounts[$s->id] ?? 0,
            ]),
        ]);
    }

    public function ranking(Request $request, string $tenantId, SchoolHouseFestPointsService $pointsService)
    {
        $user = $request->user();
        $school = Tenant::findOrFail($tenantId);
        $house = $this->resolveHouse($user, $tenantId);
        $eventId = $request->query('event_id') ? (int) $request->query('event_id') : null;

        return inertia('Portal/HouseAdmin/Ranking', [
            'school'       => $school->only('id', 'name'),
            'house'        => $house?->only('id', 'name', 'color'),
            'ranking'      => $pointsService->rankingForSchool($tenantId, $eventId),
            'events'       => $school->parent_id
                ? $pointsService->openEventsForSchool($tenantId, $school->parent_id)
                : [],
            'selectedEvent'=> $eventId,
        ]);
    }

    public function registrations(Request $request, string $tenantId)
    {
        $user = $request->user();
        $school = Tenant::findOrFail($tenantId);
        $house = $this->resolveHouse($user, $tenantId);
        $eventId = $request->query('event_id') ? (int) $request->query('event_id') : null;
        $status = $request->query('status');

        $studentIds = Student::where('tenant_id', $tenantId)
            ->when($house, fn ($q) => $q->where('school_house_id', $house->id))
            ->active()
            ->pluck('id');

        $registrations = FestRegistration::where('school_id', $tenantId)
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereHas('participants', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->with(['event', 'item', 'participants.student'])
            ->latest()
            ->limit(200)
            ->get();

        $events = $school->parent_id
            ? FestEvent::where('tenant_id', $school->parent_id)
                ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
                ->orderByDesc('event_start')
                ->get(['id', 'title', 'status', 'event_start'])
            : collect();

        return inertia('Portal/HouseAdmin/Registrations', [
            'school'        => $school->only('id', 'name'),
            'house'         => $house?->only('id', 'name', 'color'),
            'registrations' => $registrations,
            'events'        => $events,
            'filters'       => ['event_id' => $eventId, 'status' => $status],
            'statusOptions' => ['submitted', 'approved', 'rejected', 'withdrawn'],
        ]);
    }

    private function resolveHouse($user, string $tenantId): ?SchoolHouse
    {
        if ($user->hasRole('house_admin') && $user->school_house_id) {
            return SchoolHouse::where('tenant_id', $tenantId)
                ->find($user->school_house_id);
        }

        if ($user->hasAnyRole(['school_admin', 'sahodaya_admin'])) {
            return null;
        }

        abort(403);
    }
}
