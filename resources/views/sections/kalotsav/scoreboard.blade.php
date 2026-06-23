@php
    use App\Models\FestEvent;
    use App\Models\KalotsavEvent;
    use App\Services\Events\EventContext;

    $eventId = $config['fest_event_id'] ?? $config['kalotsav_event_id'] ?? null;
    $scoreboard = collect();
    $eventTitle = null;
    $eventDate = null;
    $eventLabel = 'Kalolsavam';

    if ($eventId && ($fest = FestEvent::find($eventId))) {
        $eventTitle = $fest->title;
        $eventDate = $fest->event_start;
        $eventLabel = match ($fest->event_type) {
            'sports' => 'Sports Meet',
            'kids_fest' => 'Kids Fest',
            default => 'Kalolsavam',
        };
        if ($fest->results_published) {
            $scoreboard = collect(EventContext::for($fest)->scoreboardBySchool());
        }
    } elseif (! $eventId) {
        $fest = FestEvent::where('tenant_id', $tenant->id)
            ->where('results_published', true)
            ->orderByDesc('event_start')
            ->first();
        if ($fest) {
            $eventTitle = $fest->title;
            $eventDate = $fest->event_start;
            $scoreboard = collect(EventContext::for($fest)->scoreboardBySchool());
        }
    }

    if ($scoreboard->isEmpty() && ! empty($config['kalotsav_event_id'])) {
        $legacy = KalotsavEvent::find($config['kalotsav_event_id']);
        if ($legacy?->results_published) {
            $eventTitle = $legacy->name;
            $eventDate = $legacy->event_date;
            $scoreboard = $legacy->scoreboardBySchool();
        }
    }

    if ($scoreboard->isEmpty() && empty($eventTitle)) {
        $legacy = KalotsavEvent::where('tenant_id', $tenant->id)
            ->where('results_published', true)
            ->orderByDesc('event_date')
            ->first();
        if ($legacy) {
            $eventTitle = $legacy->name;
            $eventDate = $legacy->event_date;
            $scoreboard = $legacy->scoreboardBySchool();
        }
    }
@endphp
@if($eventTitle && $scoreboard->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-10">
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">
                {{ $eventLabel }}
            </p>
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $eventTitle }}</h2>
            @if($eventDate)
            <p class="text-gray-400 mt-1 text-sm">{{ \Illuminate\Support\Carbon::parse($eventDate)->format('d M Y') }}</p>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background-color: var(--color-primary)">
                        <th class="text-left px-5 py-3 text-white font-semibold">Rank</th>
                        <th class="text-left px-5 py-3 text-white font-semibold">School</th>
                        <th class="text-right px-5 py-3 text-white font-semibold">Total Points</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($scoreboard as $i => $row)
                    @php
                        $rank = is_array($row) ? ($row['rank'] ?? $i + 1) : ($i + 1);
                        $schoolName = is_array($row) ? ($row['school_name'] ?? '') : ($row->school_name ?? '');
                        $points = is_array($row) ? ($row['total_points'] ?? 0) : ($row->total_points ?? 0);
                    @endphp
                    <tr class="{{ $i < 3 ? 'font-semibold' : '' }} hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <span class="text-lg">{{ ['🥇','🥈','🥉'][$i] ?? $rank }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-900">{{ $schoolName }}</td>
                        <td class="px-5 py-3 text-right font-bold" style="color: var(--color-primary)">
                            {{ $points }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endif
