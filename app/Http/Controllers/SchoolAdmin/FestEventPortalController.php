<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Certificate;
use App\Models\FestAppeal;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Events\EventContext;
use App\Services\Events\FestCertificateService;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use Illuminate\Http\Request;

class FestEventPortalController extends SchoolAdminController
{
    public function house(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $assignment = \App\Models\FestHouseSchool::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with('house')
            ->first();

        return $this->inertia('School/Events/House', [
            'event'           => $event->only('id', 'title', 'status'),
            'house'           => $assignment?->house,
            'houseScoreboard' => EventContext::for($event)->scoreboardByHouse(),
        ]);
    }

    public function catering(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $orders = FestCateringOrder::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->orderByDesc('meal_date')
            ->get();

        return $this->inertia('School/Events/Catering', [
            'event'  => $event->only('id', 'title'),
            'orders' => $orders,
        ]);
    }

    public function storeCatering(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'meal_date'  => 'required|date',
            'meal_type'  => 'required|in:breakfast,lunch,dinner,snacks',
            'head_count' => 'required|integer|min:1|max:5000',
            'notes'      => 'nullable|string|max:500',
        ]);

        // Prevent duplicate: same meal_date + meal_type + school already ordered.
        $exists = FestCateringOrder::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('meal_date', $data['meal_date'])
            ->where('meal_type', $data['meal_type'])
            ->exists();
        abort_if($exists, 422, 'A catering order for this meal date and type already exists.');

        FestCateringOrder::create(array_merge($data, [
            'event_id'              => $event->id,
            'school_id'             => $this->school->id,
            'status'                => 'requested',
            'submitted_by_user_id'  => $request->user()->id,
        ]));

        return back()->with('success', 'Meal request submitted.');
    }

    public function storeAppeal(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_unless($event->appeals_open, 422, 'Appeals are not open for this event.');

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'reason'         => 'required|string|max:2000',
        ]);

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->registration->school_id !== $this->school->id, 403);
        abort_unless(in_array($participant->registration->event_id, $event->reportableEventIds(), true), 403);

        // Prevent duplicate appeal for the same participant.
        $existing = FestAppeal::where('event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        abort_if($existing, 422, 'An active appeal already exists for this participant. Resolve or wait for the existing appeal to be processed.');

        FestAppeal::create([
            'event_id'              => $event->id,
            'participant_id'        => $participant->id,
            'reason'                => $data['reason'],
            'fee_amount'            => $event->appeal_fee_amount,
            'status'                => 'pending',
            'submitted_by_user_id'  => $request->user()->id,
        ]);

        return back()->with('success', 'Appeal submitted.');
    }

    public function appeals(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $appeals = FestAppeal::where('event_id', $event->id)
            ->whereHas('participant.registration', fn ($q) => $q->where('school_id', $this->school->id))
            ->with(['participant.student', 'participant.teacher', 'participant.registration.item'])
            ->latest()
            ->get();

        $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $this->school->id)
            ->with(['item', 'participants.student', 'participants.teacher'])
            ->get();

        return $this->inertia('School/Events/Appeals', [
            'school'        => $this->school->only('id', 'name'),
            'event'         => $event->only('id', 'title', 'status', 'appeals_open', 'appeal_fee_amount'),
            'appeals'       => $appeals,
            'registrations' => $registrations,
        ]);
    }

    /** @return \Illuminate\Http\RedirectResponse */
    public function festHub(string $tenantId)
    {
        $event = FestEvent::where('tenant_id', $this->school->parent_id)
            ->listedForSchool($this->school->id)
            ->orderByDesc('event_start')
            ->first();

        if (! $event) {
            return redirect("/school-admin/{$this->school->id}");
        }

        $programSlug = SchoolFestProgram::slugForEventType($event->event_type);

        $registrations = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $this->school->id)
            ->with(['item', 'participants.student', 'participants.teacher'])
            ->get();

        $appeals = FestAppeal::where('event_id', $event->id)
            ->whereHas('participant.registration', fn ($q) => $q->where('school_id', $this->school->id))
            ->with(['participant.student', 'participant.registration.item'])
            ->latest()
            ->limit(5)
            ->get();

        return $this->inertia('School/Events/FestHub', [
            'event'           => $event,
            'registrations'   => $registrations,
            'appeals'         => $appeals,
            'programSlug'     => $programSlug,
            'registrationUrl' => ProgramRouteMap::schoolRegistrationUrl($this->school->id, $programSlug),
        ]);
    }

    public function downloadCertificatesZip(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $participantIds = FestParticipant::whereHas('registration', fn ($q) => $q
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $this->school->id))
            ->pluck('id');

        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->get();

        abort_if($certificates->isEmpty(), 404, 'No certificates to download for your school.');

        $service = app(FestCertificateService::class);
        $uniqueId = $event->id.'-'.$this->school->id.'-'.bin2hex(random_bytes(4));
        $zipPath = storage_path('app/tmp/school-fest-certs-'.$uniqueId.'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($certificates as $certificate) {
            $payload = $service->payloadFor($certificate);
            $name = str($payload['student']?->name ?? 'participant')->slug().'-'.$certificate->verification_uuid.'.html';
            $html = view('fest.certificate-print', $payload)->render();
            $zip->addFromString($name, $html);
        }

        $zip->close();

        return response()->download($zipPath, str($event->title)->slug().'-certificates.zip')->deleteFileAfterSend();
    }
}
