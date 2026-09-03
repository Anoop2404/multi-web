@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-2xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Search',
            'title' => $event->title.' — Search',
        ])
        <form method="get" action="{{ route('tenant.fest.search', $event->id) }}" class="flex gap-2 mt-6 mb-2">
            <input name="q" value="{{ $q }}" placeholder="{{ $searchHint }}" class="flex-1 rounded-lg border-slate-700 bg-slate-900 text-white placeholder:text-white/30 px-3 py-2 text-sm focus:border-amber-500 focus:ring-amber-500" autofocus>
            <button class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-lg text-sm font-bold transition">Search</button>
        </form>
        @unless($nameSearch)
        <p class="text-xs text-white/40 mb-6">Names are hidden until results are published. Search by chest number or level registration number.</p>
        @else
        <p class="text-xs text-white/40 mb-6">Search by name, chest number, or level registration number.</p>
        @endunless
        <ul class="divide-y divide-slate-800 bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
            @forelse($results as $p)
            <li class="p-4">
                @if($p['link_ref'])
                <a href="{{ route('tenant.fest.participant', [$event->id, $p['link_ref']]) }}" class="font-medium text-amber-300 hover:underline">
                    @if($p['show_name'] && $p['name'])
                    <span class="uppercase">{{ $p['name'] }}</span>
                    @else
                    <span class="text-white/40 font-normal">(name hidden until results)</span>
                    @endif
                </a>
                @else
                <span class="font-medium text-white/40 font-normal">(name hidden until results)</span>
                @endif
                <p class="text-xs text-white/40">{{ $p['item_title'] ?? '' }}{{ ($p['item_title'] ?? null) && ($p['category_label'] ?? null) ? ' · ' : '' }}{{ $p['category_label'] ?? '' }}{{ (($p['item_title'] ?? null) || ($p['category_label'] ?? null)) && ($p['school'] ?? null) ? ' · ' : '' }}{{ $p['school'] ?? '' }}</p>
            </li>
            @empty
            @if($q)<li class="p-4 text-white/30 text-sm">No matches for "{{ $q }}"</li>@endif
            @endforelse
        </ul>
        <p class="mt-6"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to event</a></p>
    </div>
</section>
@endsection
