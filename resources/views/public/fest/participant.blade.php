@extends('layouts.public-event')

@section('content')
@php
    $typeLabels = ['individual' => 'Individual', 'pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
@endphp
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-2xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Participant',
            'title' => ($public['show_name'] && $public['name']) ? $public['name'] : 'Participant (identity hidden until results)',
            'subtitle' => $event->title,
        ])

        @if($public['show_name'] && ($public['photo'] || $public['school']))
        <div class="mt-4 flex items-center gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
            @if($public['photo'])
            <img src="{{ $public['photo'] }}" alt="" class="w-16 h-16 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30 shrink-0">
            @else
            <span class="w-16 h-16 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-xl border-2 border-slate-700/60 shadow-md shadow-black/30 shrink-0">
                {{ strtoupper(substr($public['name'] ?? '?', 0, 1)) }}
            </span>
            @endif
            @if($public['school'])
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-wide text-white/40 font-bold">School</p>
                <p class="font-bold text-white truncate">{{ $public['school'] }}</p>
            </div>
            @endif
        </div>
        @endif

        @if($public['team_name'] || $schedule?->sort_order || $public['scheduled_at'])
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
            @if($public['team_name'])
            <dt class="text-white/40">Team</dt><dd class="text-white/90">{{ $public['team_name'] }}</dd>
            @endif
            @if($schedule?->sort_order)
            <dt class="text-white/40">Order</dt><dd class="text-white/90">#{{ $schedule->sort_order }}</dd>
            @endif
            @if($public['scheduled_at'])
            <dt class="text-white/40">Scheduled</dt><dd class="text-white/90">{{ $public['scheduled_at']->format('d M, H:i') }} @if($public['stage'])({{ $public['stage'] }})@endif</dd>
            @endif
        </dl>
        @endif

        <div class="mt-6">
            <p class="text-xs font-bold uppercase tracking-widest text-amber-400 mb-3">Items &amp; results in this event</p>
            <ul class="space-y-2">
                @forelse($items as $row)
                <li class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-bold text-white leading-snug">{{ $row['item_title'] }}</h2>
                        <span class="shrink-0 text-[11px] font-bold uppercase tracking-wide px-2 py-1 rounded-full {{ $row['is_team_item'] ? 'bg-amber-500/10 text-amber-300 border border-amber-500/30' : 'bg-white/5 text-white/50 border border-slate-700' }}">
                            {{ $typeLabels[$row['participant_type']] ?? ucfirst($row['participant_type']) }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-center gap-2 text-sm">
                        @if($row['disqualified'])
                        <span class="text-red-400 font-semibold">Disqualified</span>
                        @elseif($row['position'])
                        @if($row['position'] <= 3)
                        <img src="{{ asset('images/fest/medals/rank-'.$row['position'].'.webp') }}" alt="" class="w-6 h-6">
                        @endif
                        <span class="font-bold text-amber-400">Position #{{ $row['position'] }}</span>
                        @if($row['grade'])<span class="text-white/50">· Grade {{ $row['grade'] }}</span>@endif
                        @if($row['result'])<span class="text-white/50">· {{ $row['result'] }}</span>@endif
                        @else
                        <span class="text-white/30">Result pending</span>
                        @endif
                    </div>

                    @if($row['results_url'])
                    <a href="{{ $row['results_url'] }}" class="inline-flex mt-3 text-xs font-bold text-amber-400 hover:underline">View full item results →</a>
                    @endif
                </li>
                @empty
                <li class="rounded-2xl border border-dashed border-slate-700 p-6 text-center text-sm text-white/30">No registered items found for this participant.</li>
                @endforelse
            </ul>
            @if(!$event->results_published)
            <p class="mt-4 text-xs text-white/30">Results appear here after official publication.</p>
            @endif
        </div>

        <p class="mt-6"><a href="{{ route('tenant.fest.search', $event->id) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Search again</a></p>
    </div>
</section>
@endsection
