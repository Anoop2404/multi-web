<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Services\Calendar\CalendarAggregationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends SahodayaAdminController
{
    public function index(Request $request, CalendarAggregationService $calendar)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->addMonths(3)->endOfMonth();

        return $this->inertia('Sahodaya/Calendar/Index', [
            'events' => $calendar->forSahodaya($this->sahodaya, Carbon::parse($from), Carbon::parse($to)),
            'filters' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'icalUrl' => "/sahodaya-admin/{$this->sahodaya->id}/calendar/export.ics?from={$from->toDateString()}&to={$to->toDateString()}",
        ]);
    }

    public function exportIcal(Request $request, CalendarAggregationService $calendar)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->addMonths(3)->endOfMonth();
        $events = $calendar->forSahodaya($this->sahodaya, Carbon::parse($from), Carbon::parse($to));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sahodaya ERP//Calendar//EN',
            'CALSCALE:GREGORIAN',
        ];

        foreach ($events as $event) {
            $start = Carbon::parse($event['start'] ?? $event['date'] ?? now())->format('Ymd\THis\Z');
            $end = Carbon::parse($event['end'] ?? $event['start'] ?? $event['date'] ?? now())->addHour()->format('Ymd\THis\Z');
            $uid = md5(($event['id'] ?? $event['title']).$start);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid.'@sahodaya';
            $lines[] = 'DTSTAMP:'.now()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$start;
            $lines[] = 'DTEND:'.$end;
            $lines[] = 'SUMMARY:'.str_replace(["\n", ',', ';'], ' ', $event['title'] ?? 'Event');
            if (! empty($event['description'])) {
                $lines[] = 'DESCRIPTION:'.str_replace(["\n", ',', ';'], ' ', $event['description']);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="sahodaya-calendar.ics"',
        ]);
    }
}
