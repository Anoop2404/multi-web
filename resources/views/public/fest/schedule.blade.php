@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-4xl mx-auto">
        @if($item)
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Item schedule',
            'title' => $item->title,
            'subtitle' => collect([$event->title, $categoryLabel ?? null, $genderLabel ?? null])->filter()->implode(' · '),
        ])
        @else
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Schedule',
            'title' => $event->title,
            'subtitle' => $event->resolvedVenueName() ? '📍 '.$event->resolvedVenueName() : null,
        ])
        @endif

        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto mt-6">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                <tr>
                    <th class="p-3">#</th>
                    <th class="p-3">Time</th>
                    @unless($item)
                    <th class="p-3">Item</th>
                    @endunless
                    <th class="p-3">Participant</th>
                    <th class="p-3">Stage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
            @forelse($schedules as $index => $row)
            <tr>
                <td class="p-3 font-mono text-xs text-white/50">{{ $index + 1 }}</td>
                <td class="p-3 text-white/80">{{ $row['scheduled_at']?->format('h:i A') ?? '—' }}</td>
                @unless($item)
                <td class="p-3">
                    @if($row['item_id'] && $row['item_title'])
                    <a href="{{ route('tenant.fest.schedule', ['event' => $event->id, 'item' => $row['item_id']]) }}" class="text-amber-400 hover:underline">{{ $row['item_title'] }}</a>
                    @if(($row['results_published_at'] ?? null) || ($isAdminPreview ?? false))
                    <a href="{{ route('tenant.fest.item-results', [$event->id, $row['item_id']]) }}" class="ml-2 text-xs text-amber-300/70 hover:underline">Results →</a>
                    @endif
                    @if(($row['category_label'] ?? null) || ($row['gender_label'] ?? null))
                    <span class="block text-xs text-white/40">{{ collect([$row['category_label'] ?? null, $row['gender_label'] ?? null])->filter()->implode(' · ') }}</span>
                    @endif
                    @else <span class="text-white/30">—</span> @endif
                </td>
                @endunless
                <td class="p-3">
                    @if($row['participant'] && $row['participant']['link_ref'])
                    <a href="{{ route('tenant.fest.participant', [$event->id, $row['participant']['link_ref']]) }}" class="text-amber-400 hover:underline">
                        @if(count($row['roster']))
                        <span class="font-semibold text-white">{{ implode(', ', array_slice($row['roster'], 0, 3)) }}</span>@if(count($row['roster']) > 3)<span class="text-white/40"> +{{ count($row['roster']) - 3 }} more</span>@endif
                        @elseif($row['roster_count'] > 0)
                        <span class="text-xs text-amber-300 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/30">{{ $row['roster_count'] > 1 ? $row['roster_count'].' Scheduled Participants' : 'Scheduled Participant' }}</span>
                        @endif
                    </a>
                    @elseif(count($row['roster']))
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
            <tr><td colspan="{{ $item ? 4 : 5 }}" class="p-6 text-center text-white/30">{{ $item ? 'No performance order for this item yet.' : 'Schedule not published yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if($item)
        <p class="mt-5 flex flex-wrap gap-5">
            <a href="{{ route('tenant.fest.schedule', $event->id) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Full schedule</a>
            @if(($item->results_published_at || ($isAdminPreview ?? false)) && !$item->results_hidden)
            <a href="{{ route('tenant.fest.item-results', [$event->id, $item->id]) }}" class="text-sm font-semibold text-amber-400 hover:underline">Results for this item →</a>
            @endif
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-white/40 hover:text-white">Event page</a>
        </p>
        @else
        <p class="mt-5"><a href="{{ route('tenant.fest.show', ['event' => $event->id]) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to event</a></p>
        @endif
    </div>
</section>
@endsection
