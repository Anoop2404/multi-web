@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-5xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => $event->title,
            'title' => $school->name,
            'subtitle' => $activeCategoryLabel
                ? $activeCategoryLabel.' results only — every item entered in this category, with rank, grade, and points.'
                : 'Full results roster — every item entered, with rank, grade, and points.',
            'badges' => [],
            'meta' => null,
        ])

        <div class="flex flex-wrap items-center justify-between gap-3 mt-4 mb-8">
            @if($activeCategory)
                <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'category' => $activeCategory]) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to {{ $activeCategoryLabel }}</a>
            @else
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'school']) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to all schools</a>
            @endif
            <span class="text-2xl font-mono font-extrabold text-amber-400">{{ $schoolRow['total_points'] }} <small class="text-xs text-white/40 font-sans font-bold uppercase tracking-wide">pts total</small></span>
        </div>

        @if($activeCategory)
        <div class="mb-8 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-amber-200">Showing <strong>{{ $activeCategoryLabel }}</strong> items only.</p>
            <a href="{{ route('tenant.fest.results.school', ['event' => $event->id, 'school' => $school->id]) }}" class="text-xs font-bold text-amber-400 hover:underline shrink-0">View full roster (all categories) →</a>
        </div>
        @endif

        @php $grouped = collect($roster)->groupBy('category'); @endphp
        <div class="space-y-10">
            @foreach($grouped as $category => $items)
            @php $categoryPoints = collect($items)->sum('points'); @endphp
            <section aria-labelledby="cat-{{ Str::slug($category) }}">
                <div class="flex items-center justify-between gap-4 mb-4 pb-2 border-b border-slate-800/80">
                    <h2 id="cat-{{ Str::slug($category) }}" class="text-sm font-bold uppercase tracking-widest text-amber-400">{{ $category }}</h2>
                    <span class="text-xs font-mono font-bold text-amber-300 bg-amber-500/10 border border-amber-500/30 px-3 py-1 rounded-full">{{ $categoryPoints }} <small class="text-white/50 font-sans font-semibold">PTS</small></span>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($items as $winner)
                    @php
                        $roster2 = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
                    @endphp
                    <article class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white/5 border-b border-slate-800">
                            <div class="min-w-0">
                                <p class="font-bold text-white">{{ $winner['item'] }}</p>
                                <p class="text-xs text-white/40">{{ $winner['participant_type'] }}</p>
                            </div>
                            @if($winner['position'] && $winner['position'] <= 3)
                                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Rank {{ $winner['position'] }}" class="w-10 h-10 shrink-0">
                            @elseif($winner['position'])
                                <span class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center text-sm font-extrabold shrink-0">#{{ $winner['position'] }}</span>
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-3 mb-4">
                                @foreach($roster2 as $member)
                                <div class="flex flex-col items-center gap-1.5">
                                    @if($member['photo'] ?? null)
                                        <img src="{{ $member['photo'] }}" alt="" class="w-20 h-20 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                                    @else
                                        <span class="w-20 h-20 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center text-xl font-bold border-2 border-slate-700/60">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                                    @endif
                                    <span class="text-xs font-semibold text-white text-center leading-tight">{{ $member['name'] ?? '—' }}</span>
                                </div>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between pt-3 border-t border-slate-800">
                                @if(!empty($winner['grade']))
                                    <span class="text-xs font-bold text-amber-400 bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30">
                                        Grade {{ $winner['grade'] }}{{ $winner['grade_points'] !== null ? ' · '.$winner['grade_points'].' pts' : '' }}
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                <span class="font-mono font-bold text-white">{{ $winner['points'] }} <small class="text-xs text-white/40">PTS</small></span>
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>
            </section>
            @endforeach
        </div>
    </div>
</section>
@endsection
