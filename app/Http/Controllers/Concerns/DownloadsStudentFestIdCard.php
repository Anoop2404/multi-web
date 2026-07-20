<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsFestIdCardResponses;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestIdCardService;
use App\Services\School\SchoolDocumentDownloadGateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

trait DownloadsStudentFestIdCard
{
    use BuildsFestIdCardResponses;

    protected function assertStudentFestIdCardAccess(FestEvent $event, Student $student, Tenant $school, ?int $headId = null): void
    {
        abort_if($student->tenant_id !== $school->id, 403);
        abort_if($event->tenant_id !== $school->parent_id, 403);

        app(SchoolDocumentDownloadGateService::class)->assertFestEventFeeForDownloads($event, $school, $headId);

        $hasRegistration = FestParticipant::query()
            ->where('student_id', $student->id)
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $event->id)
                ->where('school_id', $school->id)
                ->whereNotIn('status', ['rejected', 'withdrawn']))
            ->exists();

        abort_unless(
            $hasRegistration,
            404,
            'No fest registration found for this student.',
        );
    }

    protected function studentFestIdCardResponse(Request $request, FestEvent $event, Student $student, Tenant $school)
    {
        $defaultScope = $event->event_type === 'sports' ? 'head' : 'event';
        $scope = in_array($request->input('scope'), ['item', 'event', 'head'], true)
            ? $request->input('scope')
            : $defaultScope;

        $filters = array_merge($this->idCardFilters($request), [
            'school_id'        => $school->id,
            'student_id'       => $student->id,
            'scope'            => $scope,
            'school_downloads' => true,
        ]);

        if ($scope === 'item' && empty($filters['item_id'])) {
            $filters['item_id'] = FestParticipant::query()
                ->where('student_id', $student->id)
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('school_id', $school->id)
                    ->whereNotIn('status', ['rejected', 'withdrawn']))
                ->with('registration:id,item_id')
                ->orderBy('id')
                ->first()
                ?->registration
                ?->item_id;
        }

        // Resolve which Event Head's fee gates this download: an explicit head_id filter wins,
        // otherwise derive it from the student's registered item when the card is scoped to a
        // single head or a single item (sports events default to per-head cards).
        $headId = $filters['head_id'] ?? null;
        if ($headId === null && in_array($scope, ['head', 'item'], true)) {
            $headId = FestParticipant::query()
                ->where('student_id', $student->id)
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $event->id)
                    ->where('school_id', $school->id)
                    ->whereNotIn('status', ['rejected', 'withdrawn'])
                    ->when(! empty($filters['item_id']), fn ($qq) => $qq->where('item_id', $filters['item_id'])))
                ->with('registration.item:id,head_id')
                ->orderBy('id')
                ->first()
                ?->registration
                ?->item
                ?->head_id;
        }

        $this->assertStudentFestIdCardAccess($event, $student, $school, $headId);

        $service = app(FestIdCardService::class);
        $cards = $service->cards($event, 'student', $filters);

        abort_if($cards === [], 404, 'No ID cards available for this student and scope.');

        $cluster = Tenant::findOrFail($school->parent_id);
        $slug = str($event->title)->slug('-');
        $regSlug = str($student->reg_no ?: 'student-'.$student->id)->slug('-');
        $filename = "{$slug}-{$regSlug}-id-card.pdf";
        $customTemplate = $this->resolveCustomIdCardTemplate($event, $filters['item_id'] ?? null, 'student');

        $pdf = Pdf::loadView(
            $this->idCardSheetView($request, $customTemplate),
            $this->idCardViewData($event, $cluster, $cards, 'student', false, null, $customTemplate),
        );

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }
}
