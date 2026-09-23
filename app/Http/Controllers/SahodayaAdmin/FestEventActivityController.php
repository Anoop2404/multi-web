<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Tenant;
use App\Services\Audit\FestEventActivityService;
use App\Services\Events\PublicFestScoreboardService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestEventActivityController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $page = $request->input('page') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $schoolId = $request->input('school_id') ?: null;
        $dateFrom = $request->input('date_from') ?: null;
        $dateTo = $request->input('date_to') ?: null;
        $search = $request->input('q') ?: null;
        if ($search !== null && strtolower(trim($search)) === 'all') {
            $search = null;
        }

        $activityService = app(FestEventActivityService::class);

        // CSV Export based on active filters
        if ($request->boolean('export') || $request->input('export') === 'csv') {
            $result = $activityService->forEvent($event, 10000, $page, $itemId, $search, 0, $schoolId, $dateFrom, $dateTo);
            return $this->exportCsv($result['logs'], $event, [
                'page'      => $page,
                'item_id'   => $itemId,
                'school_id' => $schoolId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'search'    => $search,
            ]);
        }

        $limitInput = strtolower(trim((string) $request->input('limit', '200')));
        if ($limitInput === 'all') {
            $perPage = 5000;
            $pageNum = 1;
            $offset = 0;
        } else {
            $perPage = in_array((int) $limitInput, [50, 100, 200, 500], true) ? (int) $limitInput : 200;
            $pageNum = max(1, (int) ($request->input('p') ?: 1));
            $offset = ($pageNum - 1) * $perPage;
        }

        $result = $activityService->forEvent($event, $perPage, $page, $itemId, $search, $offset, $schoolId, $dateFrom, $dateTo);

        $logs = $result['logs']
            ->map(fn (array $log) => array_merge($log, [
                'page_label' => FestPageActivity::label($log['page'] ?? null),
            ]))
            ->values()
            ->all();

        $total = $result['total'];
        $lastPage = $limitInput === 'all' ? 1 : ($perPage > 0 ? (int) ceil($total / $perPage) : 1);
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + count($logs), $total);

        $scoreboards = app(PublicFestScoreboardService::class);
        $items = FestEventItem::where('event_id', $event->id)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get()
            ->map(fn (FestEventItem $it) => [
                'id'        => $it->id,
                'title'     => $it->title,
                'item_code' => $it->item_code,
                'category'  => $it->class_group ? $scoreboards->categoryLabel($event, $it->class_group) : ($it->age_group ? $scoreboards->categoryLabel($event, $it->age_group) : null),
            ]);

        $schoolIds = \App\Models\FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->pluck('school_id')
            ->filter()
            ->unique()
            ->values();

        $schools = Tenant::whereIn('id', $schoolIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->inertia('Sahodaya/Events/Activity', [
            'event'        => $event->only('id', 'title', 'event_type', 'status'),
            'activityLogs' => $logs,
            'pageLabels'   => FestPageActivity::labels(),
            'items'        => $items,
            'schools'      => $schools,
            'filters'      => [
                'page'      => $page,
                'item_id'   => $itemId,
                'school_id' => $schoolId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'q'         => $request->input('q') ?: null,
                'limit'     => $limitInput,
                'p'         => $pageNum,
            ],
            'pagination'   => [
                'total'        => $total,
                'per_page'     => $limitInput === 'all' ? 'all' : $perPage,
                'current_page' => $pageNum,
                'last_page'    => $lastPage,
                'from'         => $from,
                'to'           => $to,
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $logs
     * @param  array<string, mixed>  $filters
     */
    protected function exportCsv($logs, FestEvent $event, array $filters = []): StreamedResponse
    {
        $parts = [str($event->title)->slug()->limit(25)];
        if (! empty($filters['page'])) {
            $parts[] = str($filters['page'])->slug()->limit(15);
        }
        if (! empty($filters['item_id'])) {
            $parts[] = "item-{$filters['item_id']}";
        }
        if (! empty($filters['school_id'])) {
            $parts[] = 'school';
        }
        if (! empty($filters['search'])) {
            $parts[] = 'search-' . str($filters['search'])->slug()->limit(15);
        }
        $parts[] = 'activity-log';
        $parts[] = now()->format('Ymd-His');
        $filename = implode('-', $parts) . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Date & Time',
                'Page',
                'Action',
                'Description',
                'Item Code',
                'Item Title',
                'Category',
                'School',
                'Chest #',
                'Participant',
                'Reg #',
                'User Name',
                'User Email',
                'Actor Type',
                'IP Address',
                'Reason',
                'Details / Properties',
            ]);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log['created_at'] ?? '',
                    FestPageActivity::label($log['page'] ?? null),
                    $log['action'] ?? '',
                    $log['description'] ?? '',
                    $log['item_code'] ?? '',
                    $log['item_title'] ?? '',
                    $log['item_category'] ?? '',
                    $log['school'] ?? '',
                    $log['chest_no'] ?? '',
                    $log['participant'] ?? '',
                    $log['reg_no'] ?? '',
                    $log['user']['name'] ?? 'System',
                    $log['user']['email'] ?? '',
                    $log['actor_type'] ?? '',
                    $log['ip_address'] ?? '',
                    $log['reason'] ?? '',
                    !empty($log['properties']) ? json_encode($log['properties'], JSON_UNESCAPED_UNICODE) : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
