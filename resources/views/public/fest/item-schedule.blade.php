@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-4xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Item schedule',
            'title' => $item->title,
            'subtitle' => $categoryLabel ? $event->title.' · '.$categoryLabel : $event->title,
        ])

        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto mt-6">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                <tr>
                    <th class="p-3">Order</th>
                    <th class="p-3">Time</th>
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
                    @if($row['participant'] && $row['participant']['link_ref'])
                    <a href="{{ route('tenant.fest.participant', [$event->id, $row['participant']['link_ref']]) }}" class="text-amber-400 hover:underline">
                        @if(count($row['roster']))
                        {{ implode(', ', array_slice($row['roster'], 0, 3)) }}@if(count($row['roster']) > 3) +{{ count($row['roster']) - 3 }} more @endif
                        @elseif($row['roster_count'] > 0)
                        <span class="text-white/30 text-xs">{{ $row['roster_count'] > 1 ? $row['roster_count'].' performers (anonymous until results)' : '(anonymous until results)' }}</span>
                        @endif
                    </a>
                    @else <span class="text-white/30">—</span> @endif
                </td>
                <td class="p-3 text-white/70">{{ $row['stage'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-white/30">No performance order for this item yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <p class="mt-5 flex flex-wrap gap-5">
            <a href="{{ route('tenant.fest.schedule', $event->id) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Full schedule</a>
            @if(($item->results_published_at || ($isAdminPreview ?? false)) && !$item->results_hidden)
            <a href="{{ route('tenant.fest.item-results', [$event->id, $item->id]) }}" class="text-sm font-semibold text-amber-400 hover:underline">Results for this item →</a>
            @endif
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-white/40 hover:text-white">Event page</a>
        </p>
    </div>
</section>
@endsection
