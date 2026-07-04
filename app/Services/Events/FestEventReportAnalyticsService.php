<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\FestVolunteer;
use App\Models\FestCateringOrder;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Support\ExcelExport;
use App\Support\FestIdCardTemplates;
use App\Services\Events\FestIdCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestEventReportAnalyticsService
{
    public function __construct(public FestEvent $event) {}

    /** @return list<array<string, mixed>> */
    public function disciplineRegistrationRows(?string $schoolId = null): array
    {
        $taxonomy = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id);
        $labels = $taxonomy->labels('sport_discipline');

        $items = FestEventItem::where('event_id', $this->event->id)->get(['id', 'sport_discipline']);
        $byDiscipline = $items->groupBy(fn ($i) => $i->sport_discipline ?: 'unspecified');

        $rows = [];
        foreach ($byDiscipline as $discipline => $group) {
            $itemIds = $group->pluck('id');
            $regQuery = fn (string $status) => FestRegistration::where('event_id', $this->event->id)
                ->whereIn('item_id', $itemIds)
                ->where('status', $status)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

            $rows[] = [
                'discipline'       => $discipline,
                'discipline_label' => $labels[$discipline] ?? ($discipline === 'unspecified' ? 'Unspecified' : $discipline),
                'item_count'       => $itemIds->count(),
                'approved'         => $regQuery('approved')->count(),
                'pending'          => $regQuery('submitted')->count(),
            ];
        }

        usort($rows, fn ($a, $b) => $a['discipline_label'] <=> $b['discipline_label']);

        return $rows;
    }

    /** @return array{schools: list<string>, age_groups: list<string>, matrix: array<string, array<string, int>>, totals: array<string, int>} */
    public function ageGroupMatrix(?string $schoolId = null): array
    {
        $taxonomy = app(FestTaxonomyRegistry::class)->forTenant($this->event->tenant_id);
        $ageLabels = $taxonomy->allLabels()['age_group'] ?? [];

        $query = FestRegistration::where('fest_registrations.event_id', $this->event->id)
            ->whereIn('fest_registrations.status', ['submitted', 'approved'])
            ->join('fest_event_items', 'fest_registrations.item_id', '=', 'fest_event_items.id')
            ->when($schoolId, fn ($q) => $q->where('fest_registrations.school_id', $schoolId));

        $pairs = $query->selectRaw('fest_registrations.school_id, fest_event_items.age_group, count(*) as cnt')
            ->groupBy('fest_registrations.school_id', 'fest_event_items.age_group')
            ->get();

        $schoolIds = $pairs->pluck('school_id')->unique()->values();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $ageGroups = $pairs->pluck('age_group')->filter()->unique()->sort()->values()->all();
        $matrix = [];
        $totals = array_fill_keys($ageGroups, 0);

        foreach ($pairs as $row) {
            $age = $row->age_group ?: 'open';
            if (! in_array($age, $ageGroups, true)) {
                $ageGroups[] = $age;
            }
            $matrix[$row->school_id][$age] = (int) $row->cnt;
            $totals[$age] = ($totals[$age] ?? 0) + (int) $row->cnt;
        }

        sort($ageGroups);

        return [
            'schools'     => $schoolIds->map(fn ($id) => ['id' => $id, 'name' => $schools[$id] ?? $id])->values()->all(),
            'age_groups'  => collect($ageGroups)->map(fn ($k) => ['key' => $k, 'label' => $ageLabels[$k] ?? strtoupper($k)])->values()->all(),
            'matrix'      => $matrix,
            'totals'      => $totals,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function feeCollectionRows(): array
    {
        $schoolIds = FestSchoolEventFee::where('event_id', $this->event->id)->pluck('school_id')->unique();
        $schools = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        return FestSchoolEventFee::where('event_id', $this->event->id)
            ->with('feeReceipt')
            ->orderBy('school_id')
            ->get()
            ->map(fn (FestSchoolEventFee $fee) => [
                'school_id'   => $fee->school_id,
                'school_name' => $schools[$fee->school_id] ?? $fee->school_id,
                'total_due'   => (float) $fee->total_due,
                'paid'        => (float) ($fee->feeReceipt?->amount ?? 0),
                'status'      => $fee->status,
                'receipt_no'  => $fee->feeReceipt?->receipt_number,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function headWiseParticipantRows(?int $headId = null): array
    {
        $heads = FestItemHead::forTenant($this->event->tenant_id)
            ->forEvent($this->event->id)
            ->when($headId, fn ($q) => $q->where('id', $headId))
            ->orderBy('sort_order')
            ->get();

        $rows = [];
        foreach ($heads as $head) {
            $itemIds = FestEventItem::where('event_id', $this->event->id)
                ->where('head_id', $head->id)
                ->pluck('id');

            $participants = FestParticipant::query()
                ->whereHas('registration', fn ($q) => $q
                    ->where('event_id', $this->event->id)
                    ->whereIn('item_id', $itemIds)
                    ->where('status', 'approved'))
                ->with(['student:id,name,reg_no', 'registration.school:id,name', 'registration.item:id,title'])
                ->get();

            foreach ($participants as $p) {
                $rows[] = [
                    'head_id'    => $head->id,
                    'head_name'  => $head->name,
                    'school'     => $p->registration?->school?->name,
                    'student'    => $p->student?->name ?? $p->teacher?->name,
                    'reg_no'     => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'item'       => $p->registration?->item?->title,
                    'chest_no'   => $p->chest_no,
                    'fest_id'    => $p->level_registration_number,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function teamSquadRows(?string $schoolId = null): array
    {
        $teamItems = FestEventItem::where('event_id', $this->event->id)
            ->whereIn('participant_type', ['team', 'group'])
            ->orderBy('title')
            ->get();

        $rows = [];
        foreach ($teamItems as $item) {
            $regs = FestRegistration::where('event_id', $this->event->id)
                ->where('item_id', $item->id)
                ->where('status', 'approved')
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->with(['school:id,name', 'participants.student:id,name,reg_no', 'participants.teacher:id,name'])
                ->get();

            foreach ($regs as $reg) {
                $members = $reg->participants->map(fn ($p) => [
                    'name'   => $p->student?->name ?? $p->teacher?->name,
                    'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no,
                    'role'   => $p->participant_role ?? 'performer',
                ])->values()->all();

                $rows[] = [
                    'item_id'      => $item->id,
                    'item_title'   => $item->title,
                    'school_name'  => $reg->school?->name,
                    'member_count' => count($members),
                    'members'      => $members,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function medalTallyBySchool(): array
    {
        $service = new FestReportService($this->event);
        $marks = $service->marks();

        $bySchool = [];
        foreach ($marks as $m) {
            if (! $m->position || $m->position > 3) {
                continue;
            }
            $schoolId = $m->participant?->registration?->school_id;
            if (! $schoolId) {
                continue;
            }
            if (! isset($bySchool[$schoolId])) {
                $bySchool[$schoolId] = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0];
            }
            match ((int) $m->position) {
                1 => $bySchool[$schoolId]['gold']++,
                2 => $bySchool[$schoolId]['silver']++,
                3 => $bySchool[$schoolId]['bronze']++,
                default => null,
            };
        }

        $schoolNames = Tenant::whereIn('id', array_keys($bySchool))->pluck('name', 'id');
        $rows = [];
        foreach ($bySchool as $sid => $counts) {
            $rows[] = array_merge([
                'school_id'   => $sid,
                'school_name' => $schoolNames[$sid] ?? $sid,
            ], $counts);
        }

        usort($rows, fn ($a, $b) => ($b['gold'] <=> $a['gold']) ?: ($b['silver'] <=> $a['silver']));

        return $rows;
    }

    public function exportDisciplineRegistration(): StreamedResponse
    {
        $rows = collect($this->disciplineRegistrationRows())->map(fn ($r) => [
            $r['discipline_label'], $r['item_count'], $r['approved'], $r['pending'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-discipline-registration',
            ['Discipline', 'Items', 'Approved regs', 'Pending regs'],
            $rows,
        );
    }

    public function exportAgeGroupMatrix(?string $schoolId = null): StreamedResponse
    {
        $data = $this->ageGroupMatrix($schoolId);
        $headers = array_merge(['School'], array_column($data['age_groups'], 'label'));
        $rows = [];

        foreach ($data['schools'] as $school) {
            $row = [$school['name']];
            foreach ($data['age_groups'] as $ag) {
                $row[] = $data['matrix'][$school['id']][$ag['key']] ?? 0;
            }
            $rows[] = $row;
        }

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-age-group-matrix',
            $headers,
            collect($rows),
        );
    }

    public function exportFeePendingSchools(): StreamedResponse
    {
        $rows = collect($this->feeCollectionRows())
            ->filter(fn ($r) => ! in_array($r['status'], ['approved'], true))
            ->map(fn ($r) => [$r['school_name'], $r['total_due'], $r['status'], $r['receipt_no'] ?? '']);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-fee-pending',
            ['School', 'Due', 'Status', 'Receipt'],
            $rows,
        );
    }

    public function exportHeadWiseParticipants(?int $headId = null): StreamedResponse
    {
        $rows = collect($this->headWiseParticipantRows($headId))->map(fn ($r) => [
            $r['head_name'], $r['school'], $r['student'], $r['reg_no'], $r['item'], $r['fest_id'], $r['chest_no'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-head-wise-participants',
            ['Head', 'School', 'Participant', 'Reg no', 'Item', 'Fest ID', 'Chest'],
            $rows,
        );
    }

    public function teamSquadPdf(?string $schoolId = null): \Illuminate\Http\Response
    {
        return Pdf::loadView('fest.reports.team-squads', [
            'event' => $this->event,
            'rows'  => $this->teamSquadRows($schoolId),
        ])->download(str($this->event->title)->slug()->limit(40).'-team-squads.pdf');
    }

    public function medalTallyPdf(): \Illuminate\Http\Response
    {
        return Pdf::loadView('fest.reports.medal-tally', [
            'event' => $this->event,
            'rows'  => $this->medalTallyBySchool(),
        ])->download(str($this->event->title)->slug()->limit(40).'-medal-tally.pdf');
    }

    /** @return list<array<string, mixed>> */
    public function volunteerRosterRows(): array
    {
        return FestVolunteer::where('event_id', $this->event->id)
            ->orderBy('duty')
            ->orderBy('name')
            ->get()
            ->map(fn (FestVolunteer $v) => [
                'name'  => $v->name,
                'phone' => $v->phone,
                'duty'  => $v->duty,
                'notes' => $v->notes,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function cateringBySchoolRows(?string $schoolId = null): array
    {
        $orders = FestCateringOrder::where('event_id', $this->event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get();

        $schoolIds = $orders->pluck('school_id')->unique();
        $schoolNames = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $rows = [];
        foreach ($schoolIds as $sid) {
            $schoolOrders = $orders->where('school_id', $sid);
            $rows[] = [
                'school_id'   => $sid,
                'school_name' => $schoolNames[$sid] ?? $sid,
                'order_count' => $schoolOrders->count(),
                'total_heads' => $schoolOrders->sum('head_count'),
                'confirmed'   => $schoolOrders->where('status', 'confirmed')->count(),
                'pending'     => $schoolOrders->whereIn('status', ['pending', 'submitted'])->count(),
                'breakfast'   => $schoolOrders->where('meal_type', 'breakfast')->sum('head_count'),
                'lunch'       => $schoolOrders->where('meal_type', 'lunch')->sum('head_count'),
                'dinner'      => $schoolOrders->where('meal_type', 'dinner')->sum('head_count'),
                'snacks'      => $schoolOrders->where('meal_type', 'snacks')->sum('head_count'),
            ];
        }

        usort($rows, fn ($a, $b) => $a['school_name'] <=> $b['school_name']);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function auditLogRows(): array
    {
        $morph = (new FestEvent)->getMorphClass();
        $eventId = (string) $this->event->id;

        return AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($morph, $eventId) {
                $q->where(function ($q2) use ($morph, $eventId) {
                    $q2->where('subject_type', $morph)->where('subject_id', $eventId);
                })->orWhere('properties->event_id', $this->event->id);
            })
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get()
            ->map(fn (AuditLog $log) => [
                'created_at'  => $log->created_at?->toDateTimeString(),
                'user'        => $log->user?->name ?? $log->user?->email ?? 'System',
                'action'      => $log->action,
                'description' => $log->description,
                'page'        => $log->properties['page'] ?? '',
                'category'    => $log->category ?? '',
            ])
            ->all();
    }

    public function exportVolunteerRoster(): StreamedResponse
    {
        $rows = collect($this->volunteerRosterRows())->map(fn ($r) => [
            $r['name'], $r['phone'] ?? '', $r['duty'] ?? '', $r['notes'] ?? '',
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-volunteer-roster',
            ['Name', 'Phone', 'Duty', 'Notes'],
            $rows,
        );
    }

    public function exportCateringBySchool(?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->cateringBySchoolRows($schoolId))->map(fn ($r) => [
            $r['school_name'],
            $r['order_count'],
            $r['total_heads'],
            $r['confirmed'],
            $r['pending'],
            $r['breakfast'],
            $r['lunch'],
            $r['dinner'],
            $r['snacks'],
        ]);

        return ExcelExport::download(
            str($this->event->title)->slug()->limit(40).'-catering-by-school',
            ['School', 'Orders', 'Total heads', 'Confirmed orders', 'Pending orders', 'Breakfast heads', 'Lunch heads', 'Dinner heads', 'Snacks heads'],
            $rows,
        );
    }

    public function exportAuditLogExtract(): StreamedResponse
    {
        $rows = collect($this->auditLogRows())->map(fn ($r) => [
            $r['created_at'],
            $r['user'],
            $r['action'],
            $r['description'],
            $r['page'],
            $r['category'],
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'User', 'Action', 'Description', 'Page', 'Category']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, str($this->event->title)->slug()->limit(40).'-audit-log.csv', ['Content-Type' => 'text/csv']);
    }

    public function idCardsByHeadPdf(?int $headId = null, ?string $schoolId = null, ?string $template = null): \Illuminate\Http\Response
    {
        $service = app(FestIdCardService::class);
        $filters = array_filter([
            'school_id' => $schoolId,
        ]);

        $sections = collect($service->cardsGroupedByHead($this->event, $filters))
            ->when($headId, fn ($c) => $c->where('head_id', $headId))
            ->map(fn ($section) => [
                'item_title' => $section['head_title'],
                'cards'      => $section['cards'],
            ])
            ->values()
            ->all();

        abort_if($sections === [], 422, 'No ID cards found for the selected head / school filters.');

        $cluster = Tenant::find($this->event->tenant_id);
        $view = FestIdCardTemplates::sheetView(FestIdCardTemplates::PREMIUM);
        $slug = str($this->event->title)->slug()->limit(40);
        $headSuffix = $headId ? "-head-{$headId}" : '-all-heads';

        return Pdf::loadView($view, [
            'cards'       => [],
            'sections'    => $sections,
            'clusterName' => $cluster?->name ?? 'Sahodaya',
            'eventTitle'  => $this->event->title,
            'audience'    => 'student',
            'showTitle'   => true,
        ])->download("{$slug}{$headSuffix}-id-cards.pdf");
    }
}
