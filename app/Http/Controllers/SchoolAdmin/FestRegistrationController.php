<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Services\Events\FestRegistrationFeeService;
use Illuminate\Http\Request;

class FestRegistrationController extends SchoolAdminController
{
    public function index(Request $request, string $program = 'kalotsav', string $view = 'registration')
    {
        $typeMap = [
            'kalotsav'    => 'kalolsavam',
            'sports-meet' => 'sports',
            'kids-fest'   => 'kids_fest',
        ];

        $eventType = $typeMap[$program] ?? 'kalolsavam';
        $sahodayaId = $this->school->parent_id;

        $events = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($eventType)
            ->when($view === 'results', fn ($q) => $q->where('results_published', true))
            ->when($view === 'registration', fn ($q) => $q->whereIn('status', ['published', 'registration_open', 'ongoing']))
            ->with('items')
            ->orderByDesc('event_start')
            ->get();

        if ($view === 'results') {
            $scoreboards = [];
            foreach ($events as $event) {
                $scoreboards[$event->id] = \App\Services\Events\EventContext::for($event)->scoreboardBySchool();
            }

            return $this->inertia('School/Events/Results', [
                'program'     => $program,
                'events'      => $events,
                'scoreboards' => $scoreboards,
            ]);
        }

        $registrations = FestRegistration::where('school_id', $this->school->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->with(['event', 'item', 'participants.student', 'participants.group', 'feeReceipt'])
            ->get();

        return $this->inertia('School/Events/Registration', [
            'program'       => $program,
            'events'        => $events,
            'registrations' => $registrations,
            'students'      => Student::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name', 'reg_no']),
        ]);
    }

    public function store(Request $request, string $program)
    {
        $data = $request->validate([
            'event_id'      => 'required|exists:fest_events,id',
            'item_id'       => 'required|exists:fest_event_items,id',
            'team_name'     => 'nullable|string|max:255',
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $event = FestEvent::findOrFail($data['event_id']);
        $item = FestEventItem::findOrFail($data['item_id']);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if(! $event->isRegistrationOpen(), 422, 'Registration is closed for this event.');

        $isGroup = in_array($item->participant_type, ['group', 'team'], true);
        if ($isGroup) {
            $request->validate(['team_name' => 'required|string|max:255']);
            $count = count($data['student_ids']);
            if ($item->min_group_size && $count < $item->min_group_size) {
                return back()->with('error', "This item requires at least {$item->min_group_size} participants.");
            }
            if ($item->max_group_size && $count > $item->max_group_size) {
                return back()->with('error', "This item allows at most {$item->max_group_size} participants.");
            }
        } elseif (count($data['student_ids']) > 1) {
            return back()->with('error', 'This item allows only one participant.');
        }

        $registration = FestRegistration::create([
            'event_id'     => $data['event_id'],
            'item_id'      => $data['item_id'],
            'school_id'    => $this->school->id,
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        $groupId = null;
        if ($isGroup) {
            $group = FestGroup::create([
                'registration_id' => $registration->id,
                'team_name'       => $data['team_name'],
            ]);
            $groupId = $group->id;
        }

        foreach ($data['student_ids'] as $studentId) {
            abort_if(Student::where('id', $studentId)->where('tenant_id', $this->school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'  => $registration->id,
                'group_id'         => $groupId,
                'student_id'       => $studentId,
                'participant_type' => 'student',
            ]);
        }

        return back()->with('success', 'Registration submitted for approval.');
    }

    public function uploadPayment(Request $request, string $program, FestRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
            'bank_name'       => 'nullable|string|max:100',
        ]);

        $event = $registration->event;
        $feeService = app(FestRegistrationFeeService::class);
        if (! $feeService->feeRequired($event)) {
            return back()->with('error', 'This event does not require a registration fee.');
        }

        $feeService->attachPayment(
            $registration,
            $request->file('payment_proof'),
            $this->school->id,
            $request->user()->id,
            $data['transaction_ref'] ?? null,
            $data['bank_name'] ?? null,
        );

        return back()->with('success', 'Payment proof uploaded.');
    }
}
