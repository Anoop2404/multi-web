@php
    $eventId = $config['kalotsav_event_id'] ?? null;
    if (!$eventId) {
        // default: latest active event for this sahodaya
        $event = \App\Models\KalotsavEvent::where('tenant_id', $tenant->id)
            ->where('results_published', true)
            ->orderByDesc('event_date')
            ->first();
    } else {
        $event = \App\Models\KalotsavEvent::find($eventId);
    }

    $scoreboard = $event?->scoreboardBySchool() ?? collect();
@endphp
@if($event && $scoreboard->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-10">
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">
                {{ $event->type === 'kalotsav' ? 'Kalotsav' : ucfirst(str_replace('_',' ', $event->type)) }}
            </p>
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $event->name }}</h2>
            @if($event->event_date)
            <p class="text-gray-400 mt-1 text-sm">{{ $event->event_date->format('d M Y') }}</p>
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
                    <tr class="{{ $i < 3 ? 'font-semibold' : '' }} hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <span class="text-lg">{{ ['🥇','🥈','🥉'][$i] ?? ($i + 1) }}</span>
                        </td>
                        <td class="px-5 py-3 text-gray-900">{{ $row->school_name }}</td>
                        <td class="px-5 py-3 text-right font-bold" style="color: var(--color-primary)">
                            {{ $row->total_points }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endif
