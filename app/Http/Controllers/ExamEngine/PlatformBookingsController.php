<?php

namespace App\Http\Controllers\ExamEngine;

use App\Http\Controllers\Controller;
use App\Models\ExamEngineBooking;
use App\Services\ExamEngine\ExamEngineBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Super admin → Online exam bookings (plan §6, §9): every Sahodaya's booked live windows by day,
 * with the students live at once per time slot against platform capacity and the scale-out threshold.
 */
class PlatformBookingsController extends Controller
{
    public function index(Request $request, ExamEngineBookingService $bookings)
    {
        $tz = (string) config('app.timezone');
        $fromInput = $request->query('from');
        $from = (is_string($fromInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput)
            ? CarbonImmutable::createFromFormat('Y-m-d', $fromInput, $tz)
            : CarbonImmutable::now($tz))->startOfDay();
        $days = max(1, min(62, (int) $request->query('days', 14)));
        $to = $from->addDays($days);

        $capacity = (int) config('exam_engine.capacity');
        $threshold = (int) config('exam_engine.scale_out_threshold');

        $rows = ExamEngineBooking::query()
            ->with('tenant:id,name')
            ->where('live_starts_at', '<', $to)
            ->where('live_ends_at', '>', $from)
            ->orderBy('live_starts_at')
            ->get();

        $byDay = [];
        foreach ($rows as $booking) {
            $start = $booking->live_starts_at->copy()->timezone($tz);
            $end = $booking->live_ends_at->copy()->timezone($tz);
            $byDay[$start->format('Y-m-d')]['bookings'][] = [
                'id'             => $booking->id,
                'tenant_id'      => $booking->tenant_id,
                'sahodaya'       => $booking->tenant?->name ?? $booking->tenant_id,
                'exam_id'        => $booking->exam_id,
                'title'          => $booking->title,
                'starts_at'      => $booking->live_starts_at->toIso8601String(),
                'ends_at'        => $booking->live_ends_at->toIso8601String(),
                'window'         => $start->format('g:i A').' – '.$end->format($start->isSameDay($end) ? 'g:i A' : 'j M, g:i A'),
                'expected_count' => (int) $booking->expected_count,
                'entry_mode'     => $booking->entry_mode,
                'status'         => $booking->status,
                'active'         => in_array($booking->status, ExamEngineBooking::ACTIVE_STATUSES, true),
                'monitor_url'    => "/sahodaya-admin/{$booking->tenant_id}/mcq-exams/{$booking->exam_id}/live",
            ];
        }

        $overbooked = 0;
        $scaleOut = 0;
        foreach ($bookings->plan($from, $to) as $slot) {
            $start = CarbonImmutable::parse($slot['starts_at'])->timezone($tz);
            $end = CarbonImmutable::parse($slot['ends_at'])->timezone($tz);
            $students = (int) $slot['students'];
            $isOver = $students > $capacity;
            $needsScaleOut = $students > $threshold;
            $overbooked += $isOver ? 1 : 0;
            $scaleOut += $needsScaleOut ? 1 : 0;

            $byDay[$start->format('Y-m-d')]['slots'][] = [
                'starts_at'       => $slot['starts_at'],
                'ends_at'         => $slot['ends_at'],
                'window'          => $start->format('g:i A').' – '.$end->format($start->isSameDay($end) ? 'g:i A' : 'j M, g:i A'),
                'students'        => $students,
                'nodes'           => (int) ($slot['nodes'] ?? 1),
                'capacity'        => $capacity,
                'utilisation'     => $capacity > 0 ? (int) round($students * 100 / $capacity) : null,
                'overbooked'      => $isOver,
                'needs_scale_out' => $needsScaleOut,
                'exams'           => collect($slot['bookings'] ?? [])->map(fn (array $b) => [
                    'title'          => $b['title'] ?? '',
                    'expected_count' => (int) ($b['expected_count'] ?? 0),
                ])->values()->all(),
            ];
        }

        ksort($byDay);
        $dayList = [];
        foreach ($byDay as $date => $day) {
            $slots = $day['slots'] ?? [];
            $dayList[] = [
                'date'     => $date,
                'label'    => CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->format('D, j M Y'),
                'bookings' => $day['bookings'] ?? [],
                'slots'    => $slots,
                'peak'     => $slots === [] ? 0 : max(array_column($slots, 'students')),
                'flagged'  => collect($slots)->contains(fn (array $s) => $s['overbooked'] || $s['needs_scale_out']),
            ];
        }

        return inertia('ExamEngine/Bookings', [
            'range' => [
                'from' => $from->format('Y-m-d'),
                'to'   => $to->subDay()->format('Y-m-d'),
                'days' => $days,
                'prev' => $from->subDays($days)->format('Y-m-d'),
                'next' => $to->format('Y-m-d'),
            ],
            'capacity'          => $capacity,
            'scaleOutThreshold' => $threshold,
            'days'              => $dayList,
            'totals'            => [
                'bookings'         => $rows->count(),
                'active'           => $rows->whereIn('status', ExamEngineBooking::ACTIVE_STATUSES)->count(),
                'students'         => (int) $rows->whereIn('status', ExamEngineBooking::ACTIVE_STATUSES)->sum('expected_count'),
                'overbooked_slots' => $overbooked,
                'scale_out_slots'  => $scaleOut,
            ],
        ]);
    }
}
