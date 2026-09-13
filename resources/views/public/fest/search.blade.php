@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-2xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Search',
            'title' => $event->title.' — Search',
        ])
        <form method="get" action="{{ route('tenant.fest.search', $event->id) }}" class="mt-6 mb-2">
            <label for="participant-search" class="block text-xs font-bold text-white/60 mb-2">Participant name or public number</label>
            <div class="flex gap-2">
                <input id="participant-search" type="search" name="q" value="{{ $q }}" placeholder="{{ $searchHint }}" class="min-w-0 flex-1 rounded-lg border-slate-700 bg-slate-900 text-white placeholder:text-white/30 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-500" autofocus>
                <button class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-lg text-sm font-bold transition">Search</button>
            </div>
        </form>
        @unless($nameSearch)
        <p class="text-xs text-white/40 mb-6">Names are hidden until results are published. Search by chest number or level registration number.</p>
        @else
        <p class="text-xs text-white/40 mb-6">Search by name, chest number, or level registration number.</p>
        @endunless
        @if($q)
        <p class="text-xs text-white/50 mb-3" aria-live="polite">{{ $results->count() }} {{ Str::plural('participant', $results->count()) }} found</p>
        @endif
        <ul class="divide-y divide-slate-800 bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
            @forelse($results as $p)
            <li class="p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    @if($p['photo'] ?? null)
                    <img src="{{ $p['photo'] }}" alt="" class="w-12 h-12 rounded-xl object-cover object-top border border-slate-700 shrink-0">
                    @else
                    <span class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-300 border border-amber-500/20 flex items-center justify-center font-bold shrink-0" aria-hidden="true">{{ ($p['show_name'] && $p['name']) ? Str::upper(Str::substr($p['name'], 0, 1)) : '?' }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        @if($p['link_ref'])
                        <a href="{{ route('tenant.fest.participant', [$event->id, $p['link_ref']]) }}" class="font-bold text-amber-300 hover:underline uppercase break-words">
                            {{ ($p['show_name'] && $p['name']) ? $p['name'] : 'Participant details' }}
                        </a>
                        @else
                        <span class="font-medium text-white/40">Name hidden until results</span>
                        @endif
                        <p class="text-xs text-white/45 mt-0.5">{{ collect([$p['reference'] !== '—' ? $p['reference'] : null, $p['school'] ?? null])->filter()->implode(' · ') }}</p>
                        @if(!empty($p['matched_items']))
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @foreach($p['matched_items'] as $matchedItem)
                            <span class="rounded-full border border-slate-700 bg-white/5 px-2 py-1 text-[10px] text-white/60">{{ $matchedItem['title'] }}@if($matchedItem['category']) · {{ $matchedItem['category'] }}@endif</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @if(($p['item_count'] ?? 0) > 1)
                    <span class="shrink-0 rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-1 text-[10px] font-bold text-amber-300">{{ $p['item_count'] }} items</span>
                    @endif
                </div>
            </li>
            @empty
            @if($q)<li class="p-4 text-white/30 text-sm">No matches for "{{ $q }}"</li>@endif
            @endforelse
        </ul>
        <p class="mt-6"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to event</a></p>
    </div>
</section>
@endsection
