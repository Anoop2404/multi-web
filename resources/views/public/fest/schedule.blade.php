@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-4xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Schedule',
            'title' => $event->title,
            'subtitle' => $event->resolvedVenueName() ? '📍 '.$event->resolvedVenueName() : null,
        ])

        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto mt-6">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                <tr>
                    <th class="p-3">Order</th>
                    <th class="p-3">Time</th>
                    <th class="p-3">Item</th>
                    <th class="p-3">Participant</th>
                    <th class="p-3">Stage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
            @forelse($schedules as $row)
            <tr>
                <td class="p-3 font-mono text-xs text-white/50">{{ $row['sort_order'] ?? '—' }}</td>
                <td class="p-3 text-white/80">{{ $row['scheduled_at']?->format('H:i') ?? '—' }}</td>
                <td class="p-3">
                    @if($row['item_id'] && $row['item_title'])
                    <a href="{{ route('tenant.fest.item-schedule', [$event->id, $row['item_id']]) }}" class="text-amber-400 hover:underline">{{ $row['item_title'] }}</a>
                    @if(($row['results_published_at'] ?? null) || ($isAdminPreview ?? false))
                    <a href="{{ route('tenant.fest.item-results', [$event->id, $row['item_id']]) }}" class="ml-2 text-xs text-amber-300/70 hover:underline">Results →</a>
                    @endif
                    @if($row['category_label'] ?? null)
                    <span class="block text-xs text-white/40">{{ $row['category_label'] }}</span>
                    @endif
                    @else <span class="text-white/30">—</span> @endif
                </td>
                <td class="p-3">
                    @if(count($row['roster']))
                        <span class="font-semibold text-white">{{ implode(', ', array_slice($row['roster'], 0, 3)) }}</span>
                        @if(count($row['roster']) > 3)<span class="text-white/40"> +{{ count($row['roster']) - 3 }} more</span>@endif
                    @elseif($row['roster_count'] > 0)
                        <span class="text-xs text-amber-300 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/30">{{ $row['roster_count'] > 1 ? $row['roster_count'].' Scheduled Participants' : 'Scheduled Participant' }}</span>
                    @else
                        <span class="text-white/30">—</span>
                    @endif
                </td>
                <td class="p-3 text-white/70">{{ $row['stage'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-white/30">Schedule not published yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <p class="mt-5"><a href="{{ route('tenant.fest.show', ['event' => $event->id]) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to event</a></p>
    </div>
</section>
@endsection
