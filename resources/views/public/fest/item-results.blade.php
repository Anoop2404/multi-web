@extends('layouts.public-event')

@section('content')
@php
    $rankTint = [
        1 => 'border-amber-500/40 bg-gradient-to-b from-amber-500/10 to-slate-900/60',
        2 => 'border-slate-400/30 bg-gradient-to-b from-slate-400/10 to-slate-900/60',
        3 => 'border-orange-700/30 bg-gradient-to-b from-orange-700/10 to-slate-900/60',
    ];
    $typeLabels = ['individual' => 'Individual', 'pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
@endphp
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-[100rem] mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Results',
            'title' => Str::upper($item->title),
            'subtitle' => $event->title,
            'badges' => collect([$categoryLabel ?? null, $genderLabel ?? null, $typeLabels[$item->participant_type] ?? ucfirst($item->participant_type ?: 'individual')])->filter()->all(),
        ])

        @if($allMarks->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-700 p-10 text-center text-white/30 mt-6">No published results for this item.</div>
        @else
        @if($marks->isNotEmpty())
        <div class="mt-6 flex flex-wrap items-end justify-between gap-2">
            <div class="flex items-center gap-2 text-amber-300/90">
                <span class="text-lg" aria-hidden="true">🏆</span>
                <h2 class="text-xs font-bold uppercase tracking-wider">Winner Roster</h2>
            </div>
            <p class="text-xs text-white/40">Podium finishers · ties included</p>
        </div>
        <div class="mt-3 h-px bg-gradient-to-r from-amber-500/40 via-slate-700 to-transparent"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4 mt-4">
            @foreach($marks as $row)
            @php
                $roster = ($row['team'] ?? []) ?: [['name' => $row['participant'], 'photo' => $row['photo'] ?? null]];
                $pos = $row['position'];
                $isLargeTeam = count($roster) > 4;
            @endphp
            <article class="rounded-2xl border {{ $rankTint[$pos] ?? 'border-slate-800 bg-slate-900/60' }} {{ $isLargeTeam ? 'md:col-span-2 xl:col-span-3' : '' }} overflow-hidden">
                <div class="flex items-center gap-3 p-4 border-b border-white/10">
                    @include('public.fest.partials.rank-medal', ['position' => $pos, 'size' => 58])
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-white/40">Rank {{ $pos }}</p>
                        <p class="font-bold text-sm leading-snug text-white uppercase break-words">{{ $row['school'] ?? '—' }}</p>
                        @if(count($roster) > 1)<p class="text-[11px] text-white/40 mt-0.5">{{ count($roster) }} members</p>@endif
                    </div>
                    @if($row['poster_url'] ?? null)
                    <a href="{{ $row['poster_url'] }}" class="shrink-0 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-bold text-amber-300 hover:bg-white/10" target="_blank" rel="noopener">Poster</a>
                    @endif
                </div>
                <div class="p-4 grid {{ $isLargeTeam ? 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' : 'grid-cols-1' }} gap-3">
                    @foreach($roster as $member)
                    <div class="flex items-center gap-3 min-w-0">
                        @if($member['photo'] ?? null)
                        <img src="{{ $member['photo'] }}" alt="" class="w-16 h-16 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30 shrink-0">
                        @else
                        <span class="w-16 h-16 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-lg border-2 border-slate-700/60 shrink-0" aria-hidden="true">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                        <span class="text-sm font-bold leading-snug text-white uppercase break-words">{{ $member['name'] ?? '—' }}</span>
                    </div>
                    @endforeach
                </div>
            </article>
            @endforeach
        </div>
        @endif

        <div class="mt-10 flex items-center gap-2 text-amber-300/90">
            <span class="text-lg">📋</span>
            <h2 class="text-xs font-bold uppercase tracking-wider">Full Results</h2>
        </div>
        <div class="mt-3 h-px bg-gradient-to-r from-amber-500/40 via-slate-700 to-transparent"></div>

        <div class="mt-4 md:hidden space-y-3">
            @foreach($allMarks as $row)
            @php $names = !empty($row['team']) ? collect($row['team'])->pluck('name')->filter()->implode(', ') : $row['participant']; @endphp
            <article class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl bg-white/5 border border-slate-700 flex items-center justify-center font-mono font-extrabold text-amber-300 shrink-0">#{{ $row['position'] ?? '—' }}</span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-white uppercase leading-snug break-words">{{ $names ?: '—' }}</h3>
                        <p class="text-xs text-white/45 uppercase mt-1 break-words">{{ $row['school'] ?? '—' }}</p>
                    </div>
                    <span class="font-mono font-extrabold text-white shrink-0">{{ $row['points'] ?? 0 }} <small class="text-[10px] text-white/40">PTS</small></span>
                </div>
                <dl class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-800 text-center">
                    <div><dt class="text-[9px] uppercase tracking-wide text-white/35">Rank pts</dt><dd class="text-xs font-mono text-white/70 mt-1">{{ $row['rank_points'] ?? '—' }}</dd></div>
                    <div><dt class="text-[9px] uppercase tracking-wide text-white/35">Grade</dt><dd class="text-xs font-bold text-amber-300 mt-1">{{ $row['grade'] ?? '—' }}</dd></div>
                    <div><dt class="text-[9px] uppercase tracking-wide text-white/35">Grade pts</dt><dd class="text-xs font-mono text-white/70 mt-1">{{ $row['grade_points'] ?? '—' }}</dd></div>
                </dl>
            </article>
            @endforeach
        </div>

        <div class="mt-4 hidden md:block rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-white/5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="p-3 w-12 text-center">Rank</th>
                            <th class="p-3">Participant</th>
                            <th class="p-3">School</th>
                            <th class="p-3 text-right w-20">Rank Pts</th>
                            <th class="p-3 text-center w-20">Grade</th>
                            <th class="p-3 text-right w-20">Grade Pts</th>
                            <th class="p-3 text-right w-24">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($allMarks as $row)
                        @php
                            $names = !empty($row['team']) ? collect($row['team'])->pluck('name')->filter()->implode(', ') : $row['participant'];
                        @endphp
                        <tr class="hover:bg-white/5 transition">
                            <td class="p-3 text-center font-mono text-slate-400">{{ $row['position'] ?? '—' }}</td>
                            <td class="p-3 font-semibold text-white uppercase">{{ $names ?: '—' }}</td>
                            <td class="p-3 text-white/50 uppercase">{{ $row['school'] ?? '—' }}</td>
                            <td class="p-3 text-right font-mono text-white/70">{{ $row['rank_points'] ?? '—' }}</td>
                            <td class="p-3 text-center font-bold text-amber-300">{{ $row['grade'] ?? '—' }}</td>
                            <td class="p-3 text-right font-mono text-white/70">{{ $row['grade_points'] ?? '—' }}</td>
                            <td class="p-3 text-right font-mono font-bold text-white">{{ $row['points'] ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <p class="mt-6"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-white/40 hover:text-white">← Event page</a></p>
    </div>
</section>
@endsection
