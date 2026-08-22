@extends('layouts.public-event')

@section('content')
@php
    $rankTint = [
        1 => 'border-amber-500/40 bg-gradient-to-b from-amber-500/10 to-slate-900/60',
        2 => 'border-slate-400/30 bg-gradient-to-b from-slate-400/10 to-slate-900/60',
        3 => 'border-orange-700/30 bg-gradient-to-b from-orange-700/10 to-slate-900/60',
        4 => 'border-blue-600/30 bg-gradient-to-b from-blue-600/10 to-slate-900/60',
        5 => 'border-emerald-600/30 bg-gradient-to-b from-emerald-600/10 to-slate-900/60',
        6 => 'border-slate-500/30 bg-gradient-to-b from-slate-500/10 to-slate-900/60',
    ];
    $typeLabels = ['individual' => 'Individual', 'pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
@endphp
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-6xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Results',
            'title' => $item->title,
            'subtitle' => $event->title,
            'badges' => [$typeLabels[$item->participant_type] ?? ucfirst($item->participant_type ?: 'individual')],
        ])

        @if($marks->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-700 p-10 text-center text-white/30 mt-6">No published results for this item.</div>
        @else
        <div class="mt-6 flex items-center gap-2 text-amber-300/90">
            <span class="text-lg">🏆</span>
            <h2 class="text-xs font-bold uppercase tracking-wider">Winner Roster</h2>
        </div>
        <div class="mt-3 h-px bg-gradient-to-r from-amber-500/40 via-slate-700 to-transparent"></div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-4">
            @foreach($marks as $row)
            @php
                $roster = ($row['team'] ?? []) ?: [['name' => $row['participant'], 'photo' => $row['photo'] ?? null]];
                $pos = $row['position'];
                // Individual/pair keep a distinct row (mobile) / portrait-card (desktop) treatment.
                // 3+ gets the header+grid team layout. Only large rosters (7+) get the full-width
                // card — a small trio/group at full width left a big empty gap beside 3-4 tiles.
                $compact = count($roster) <= 2;
                $wide = count($roster) > 6;
            @endphp
            <div class="rounded-2xl border {{ $rankTint[$pos] ?? 'border-slate-800 bg-slate-900/60' }} {{ $compact ? '' : ($wide ? 'sm:col-span-2 lg:col-span-4' : 'sm:col-span-2') }} overflow-hidden">

                {{-- One shared photo-tile language everywhere: rounded-xl (never a circle), the
                     same border/shadow treatment, sized per tier but never smaller than feels
                     like a "standard" size — individual/pair get the largest tier since there
                     are only 1-2 of them; team tiles are smaller only because 11+ have to wrap. --}}
                @if($compact)
                    {{-- Individual/pair: ONE structure at every width — medal, photo(s) with the
                         name captioned directly under each, school once under the whole group —
                         just scaled up at sm+. No separate mobile/desktop markup to drift apart. --}}
                    <div class="flex items-center gap-3 sm:gap-4 p-4 sm:p-5">
                        <div class="shrink-0">
                            @if($pos && $pos <= 6)
                                <span class="sm:hidden">@include('public.fest.partials.rank-medal', ['position' => $pos, 'size' => 56])</span>
                                <span class="hidden sm:inline-block">@include('public.fest.partials.rank-medal', ['position' => $pos, 'size' => 80])</span>
                            @else
                                <div class="w-14 h-14 sm:w-20 sm:h-20 rounded-full bg-slate-700 flex items-center justify-center text-white font-black">{{ $pos ? '#' . $pos : '—' }}</div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex gap-3 sm:gap-4">
                                @foreach($roster as $member)
                                <div class="flex flex-col items-center gap-1.5 flex-1 min-w-0 max-w-[7rem] sm:max-w-[9rem]">
                                    @if($member['photo'] ?? null)
                                    <img src="{{ $member['photo'] }}" alt="" class="w-full h-24 sm:h-32 rounded-xl object-cover border-2 border-slate-700/60 shadow-md shadow-black/30">
                                    @else
                                    <span class="w-full h-24 sm:h-32 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-xl sm:text-2xl border-2 border-slate-700/60 shadow-md shadow-black/30">
                                        {{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}
                                    </span>
                                    @endif
                                    <span class="text-xs sm:text-sm font-bold leading-snug text-white text-center line-clamp-2">{{ $member['name'] ?? '—' }}</span>
                                </div>
                                @endforeach
                            </div>
                            <p class="text-xs text-white/40 mt-3 truncate">{{ $row['school'] }}</p>
                        </div>
                    </div>
                @else
                    {{-- Team/group (3+): header line (rank, school, member count) then a wrapping
                         grid of standard photo tiles — same shape/border as individual/pair,
                         sized down just enough that 11+ members still wrap cleanly. --}}
                    <div class="p-4 sm:p-5">
                        <div class="flex items-center gap-3">
                            @if($pos && $pos <= 6)
                                @include('public.fest.partials.rank-medal', ['position' => $pos, 'size' => 64])
                            @else
                                <div class="shrink-0 w-16 h-16 rounded-full bg-slate-700 flex items-center justify-center text-sm text-white font-black">{{ $pos ? '#' . $pos : '—' }}</div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm text-white truncate">{{ $row['school'] }}</p>
                                <p class="text-[11px] text-white/40">{{ count($roster) }} Team Members</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-3 justify-items-center">
                            @foreach($roster as $member)
                            <div class="flex flex-col items-center gap-1.5 w-20">
                                @if($member['photo'] ?? null)
                                <img src="{{ $member['photo'] }}" alt="" class="w-20 h-20 rounded-xl object-cover border-2 border-slate-700/60 shadow-md shadow-black/30">
                                @else
                                <span class="w-20 h-20 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-lg border-2 border-slate-700/60 shadow-md shadow-black/30">
                                    {{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}
                                </span>
                                @endif
                                <span class="text-[11px] font-semibold leading-tight text-white/80 text-center line-clamp-2">{{ $member['name'] ?? '—' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <p class="mt-6"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-white/40 hover:text-white">← Event page</a></p>
    </div>
</section>
@endsection
