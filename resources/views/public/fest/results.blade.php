@extends('layouts.public-event')

@section('content')
@php
    $tabs = [
        'toppers' => 'Toppers',
        'school' => 'School-wise',
        'category' => 'Category-wise',
        'item' => 'Item-wise',
        'individual' => 'Individual',
        'championship' => 'Championship',
    ];
@endphp

<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-6xl mx-auto">
        @php
            $heroBadges = array_values(array_filter([$eventContext['phase'], $eventContext['region'], $event->resolvedVenueName()]));
        @endphp
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Published Results',
            'title' => $event->title,
            'subtitle' => 'Browse official toppers and results by school, category, item, or participant.',
            'badges' => $heroBadges,
            'meta' => ($publishedAt ?? null) ? 'Results published on '.\Carbon\Carbon::parse($publishedAt)->format('d M Y, g:i A') : null,
        ])

        <div class="flex justify-end mt-4">
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id]) }}" class="text-sm font-semibold text-amber-400 hover:underline shrink-0">Scoreboard →</a>
        </div>

        <nav class="sticky top-16 z-20 flex gap-2 overflow-x-auto mt-4 mb-8 rounded-2xl border border-slate-800 bg-slate-900/90 backdrop-blur p-2 shadow-xl" aria-label="Result views">
            @foreach($tabs as $key => $label)
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => $key]) }}"
                   class="shrink-0 px-4 py-2 rounded-xl text-sm border font-semibold transition {{ $tab === $key ? 'bg-amber-500 text-slate-950 border-amber-500' : 'bg-transparent text-white/60 border-transparent hover:border-amber-500/40 hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if($tab === 'toppers')
            @php $podium = [1 => '1st', 2 => '2nd', 3 => '3rd']; @endphp
            <div class="space-y-10">
                <section aria-labelledby="overall-school-toppers">
                    <div class="mb-4"><p class="text-xs font-bold uppercase tracking-widest text-amber-400">Overall championship</p><h2 id="overall-school-toppers" class="text-2xl font-bold mt-1 text-white">School Overall Toppers</h2></div>
                    <div class="grid md:grid-cols-3 gap-4">
                        @forelse($overallSchoolToppers as $row)
                        <article class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-5 {{ $row['rank'] == 1 ? 'border-amber-500/40 bg-gradient-to-br from-amber-500/10 to-slate-900/60' : '' }}">
                            <div class="flex items-start justify-between gap-3"><span class="text-xs font-extrabold uppercase tracking-widest text-amber-400">{{ $podium[$row['rank']] ?? '#'.$row['rank'] }}</span><span class="font-mono text-2xl font-extrabold text-white">{{ $row['total_points'] }} <small class="text-[10px] text-white/40">PTS</small></span></div>
                            <h3 class="font-bold text-lg leading-snug mt-5 text-white uppercase">{{ $row['school_name'] }}</h3>
                            @if($lockedCumulativeStanding ?? null)
                            <dl class="grid {{ $showPhasePoints ? 'grid-cols-4' : 'grid-cols-3' }} gap-1 mt-4 pt-3 border-t border-slate-800 text-center">
                                <div><dt class="text-[9px] uppercase text-white/30">Opening</dt><dd class="font-mono text-sm font-bold text-white">{{ $row['opening_points'] }}</dd></div>
                                <div><dt class="text-[9px] uppercase text-white/30">Event</dt><dd class="font-mono text-sm font-bold text-sky-400">+{{ $row['event_points'] }}</dd></div>
                                @if($showPhasePoints)<div><dt class="text-[9px] uppercase text-white/30">Phase</dt><dd class="font-mono text-sm font-bold text-indigo-400">+{{ $row['phase_points'] }}</dd></div>@endif
                                <div><dt class="text-[9px] uppercase text-white/30">Closing</dt><dd class="font-mono text-sm font-bold text-amber-400">{{ $row['closing_points'] }}</dd></div>
                            </dl>
                            @endif
                        </article>
                        @empty
                        <p class="md:col-span-3 rounded-2xl border border-dashed border-slate-700 p-8 text-center text-white/40">No school toppers published yet.</p>
                        @endforelse
                    </div>
                </section>

                <section aria-labelledby="school-category-toppers">
                    <div class="mb-4"><p class="text-xs font-bold uppercase tracking-widest text-amber-400">Category leaders</p><h2 id="school-category-toppers" class="text-2xl font-bold mt-1 text-white">School Category-wise Toppers</h2></div>
                    <div class="grid lg:grid-cols-2 gap-4">
                        @forelse($schoolCategoryToppers as $board)
                        <article class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                            <h3 class="font-bold px-4 py-3 bg-white/5 border-b border-slate-800 text-white">{{ $board['label'] }}</h3>
                            <ol class="divide-y divide-slate-800">
                                @foreach($board['rows'] as $row)
                                <li class="flex items-center gap-3 px-4 py-3"><span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs font-extrabold">{{ $row['rank'] }}</span><span class="font-semibold text-sm flex-1 min-w-0 text-white uppercase">{{ $row['school_name'] }}</span><span class="font-mono font-bold text-white">{{ $row['total_points'] }}</span></li>
                                @endforeach
                            </ol>
                        </article>
                        @empty
                        <p class="lg:col-span-2 rounded-2xl border border-dashed border-slate-700 p-8 text-center text-white/40">No school category toppers published yet.</p>
                        @endforelse
                    </div>
                </section>

                <section aria-labelledby="student-category-toppers">
                    <div class="mb-4"><p class="text-xs font-bold uppercase tracking-widest text-amber-400">Student championship</p><h2 id="student-category-toppers" class="text-2xl font-bold mt-1 text-white">Student Category-wise Toppers</h2></div>
                    <div class="grid lg:grid-cols-2 gap-4">
                        @forelse($studentCategoryToppers as $group)
                        <article class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                            <h3 class="font-bold px-4 py-3 bg-white/5 border-b border-slate-800 text-white">{{ $group['label'] }}</h3>
                            <ol class="divide-y divide-slate-800">
                                @foreach($group['rows'] as $row)
                                <li class="flex items-center gap-3 px-4 py-3">
                                    @if($row['photo'] ?? null)<img src="{{ $row['photo'] }}" alt="" class="w-10 h-10 rounded-xl object-cover object-top border border-slate-700 shrink-0">@else<span class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-sm font-extrabold text-amber-400 shrink-0">{{ strtoupper(substr($row['student'] ?? '?', 0, 1)) }}</span>@endif
                                    <div class="min-w-0 flex-1"><p class="font-bold text-sm text-white uppercase"><span class="text-amber-400 mr-1">#{{ $row['category_rank'] }}</span>{{ $row['student'] }}</p><p class="text-xs text-white/40 mt-0.5 uppercase">{{ $row['school'] }}</p></div><span class="font-mono font-bold text-white">{{ $row['points'] }}</span>
                                </li>
                                @endforeach
                            </ol>
                        </article>
                        @empty
                        <p class="lg:col-span-2 rounded-2xl border border-dashed border-slate-700 p-8 text-center text-white/40">Student championship toppers will appear after category points are published.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @elseif($tab === 'school')
            @if($lockedCumulativeStanding ?? null)
                <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">Championship standing after {{ $lockedCumulativeStanding['phase']->name }}</p>
                        <p class="text-xs text-emerald-400/80 mt-1">Opening + this phase = locked cumulative closing balance.</p>
                    </div>
                    <span class="text-xs font-bold text-emerald-300">Snapshot v{{ $lockedCumulativeStanding['version'] }}</span>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                            <tr>
                                <th class="p-3">Rank</th><th class="p-3">School</th>
                                <th class="p-3 text-right">Opening</th><th class="p-3 text-right">This Event</th>
                                @if($showPhasePoints)<th class="p-3 text-right">This Phase</th>@endif<th class="p-3 text-right">Closing</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($schoolBoard as $row)
                                <tr>
                                    <td class="p-3 font-bold text-amber-400">#{{ $row['rank'] }}</td>
                                    <td class="p-3 font-semibold text-white uppercase">{{ $row['school_name'] }}</td>
                                    <td class="p-3 text-right font-mono text-white/70">{{ $row['opening_points'] }}</td>
                                    <td class="p-3 text-right font-mono text-sky-400">+{{ $row['event_points'] }}</td>
                                    @if($showPhasePoints)<td class="p-3 text-right font-mono text-indigo-400">+{{ $row['phase_points'] }}</td>@endif
                                    <td class="p-3 text-right font-mono font-bold text-white">{{ $row['closing_points'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $showPhasePoints ? 6 : 5 }}" class="p-8 text-center text-white/30">No cumulative points published yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            {{-- §7.3a (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): phased events show
                 the cumulative overall (sum of every published phase's points, revealed
                 progressively as phases publish) instead of the plain school board.
                 $phaseCumulativeBoard is null for every non-phased event and for any
                 non-'overall' scope — falls straight through to the unchanged table below,
                 exactly today's display. --}}
            @elseif(($phaseCumulativeBoard ?? null) !== null)
                <div class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-300">Cumulative overall standing</p>
                    <p class="text-xs text-amber-400/80 mt-1">
                        Running total across published phases so far —
                        @if(count($phaseBreakdown ?? []))
                            {{ collect($phaseBreakdown)->map(fn ($p) => $p['name'].($p['results_published'] ? '' : ' (not yet published)'))->implode(', ') }}.
                        @else
                            no phases published yet.
                        @endif
                    </p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                            <tr>
                                <th class="p-3">Rank</th>
                                <th class="p-3">School</th>
                                @foreach(collect($phaseBreakdown ?? [])->where('results_published', true) as $phase)
                                    <th class="p-3 text-right">{{ $phase['name'] }}</th>
                                @endforeach
                                <th class="p-3 text-right">Cumulative Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($phaseCumulativeBoard as $row)
                                <tr>
                                    <td class="p-3 font-bold text-amber-400">#{{ $row['rank'] }}</td>
                                    <td class="p-3 font-semibold text-white uppercase">{{ $row['school_name'] }}</td>
                                    @foreach(collect($phaseBreakdown ?? [])->where('results_published', true) as $phase)
                                        <td class="p-3 text-right font-mono text-white/70">{{ $row['phase_points'][$phase['phase_id']] ?? 0 }}</td>
                                    @endforeach
                                    <td class="p-3 text-right font-mono font-bold text-white">{{ $row['total_points'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 2 + collect($phaseBreakdown ?? [])->where('results_published', true)->count() }}" class="p-8 text-center text-white/30">No phase results published yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                        <tr>
                            <th class="p-3">School</th>
                            <th class="p-3 text-right">Points</th>
                            <th class="p-3 w-10"><span class="sr-only">View full roster</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($schoolBoard as $row)
                            <tr>
                                <td class="p-3 font-semibold text-white uppercase">
                                    <button type="button" data-jump-to-school="{{ $row['school_id'] }}" class="hover:text-amber-400 hover:underline text-left">{{ $row['school_name'] }}</button>
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-base text-white">{{ $row['total_points'] }}</td>
                                <td class="p-3 text-right">
                                    <a href="{{ route('tenant.fest.results.school', ['event' => $event->id, 'school' => $row['school_id']]) }}"
                                       title="View {{ $row['school_name'] }}'s full roster" aria-label="View {{ $row['school_name'] }}'s full roster"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/40 hover:text-amber-400 hover:bg-white/5 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-8 text-center text-white/30">No school points published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @endif

            @if(!empty($schoolWinnersBoard))
            <section class="mt-10" aria-labelledby="school-winners">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-amber-400">Full roster</p>
                        <h2 id="school-winners" class="text-2xl font-bold mt-1 text-white">School-wise Results</h2>
                        <p class="text-xs text-white/40 mt-1">Choose a school to see every item it entered, with rank, grade, and points.</p>
                    </div>
                    <label class="w-full sm:w-64">
                        <span class="sr-only">Choose a school</span>
                        <select id="school-winner-picker" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">All schools</option>
                            @foreach($schoolWinnersBoard as $row)
                                <option value="{{ $row['school_id'] }}">{{ $row['school_name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid lg:grid-cols-2 gap-4" id="school-winner-cards">
                    @foreach($schoolWinnersBoard as $row)
                    <article class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden" data-school-winner-card data-school-id="{{ $row['school_id'] }}">
                        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white/5 border-b border-slate-800">
                            <h3 class="font-bold text-white uppercase">{{ $row['school_name'] }}</h3>
                            <span class="text-xs font-bold text-amber-400 shrink-0">{{ $row['total_points'] }} pts</span>
                        </div>
                        <ol class="divide-y divide-slate-800">
                            @foreach($row['winners'] as $winner)
                            @php
                                // Same roster fallback the Item-wise tab above uses: the
                                // full team for a group/team item, or a one-person "team"
                                // built from participant/photo for an individual item.
                                $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
                                $rosterNames = collect($roster)->pluck('name')->filter()->values();
                            @endphp
                            <li class="flex items-start gap-3 px-4 py-2.5">
                                @if($winner['position'] && $winner['position'] <= 3)
                                    <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Rank {{ $winner['position'] }}" class="w-6 h-6 shrink-0 mt-0.5">
                                @else
                                    <span class="w-6 h-6 rounded-full bg-white/5 text-white/40 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">{{ $winner['position'] ? '#'.$winner['position'] : '—' }}</span>
                                @endif
                                <div class="flex -space-x-2 shrink-0 mt-0.5">
                                    @foreach(collect($roster)->take(3) as $member)
                                        @if($member['photo'] ?? null)
                                            <img src="{{ $member['photo'] }}" alt="" class="w-6 h-6 rounded-full object-cover object-top border border-slate-900">
                                        @else
                                            <span class="w-6 h-6 rounded-full bg-amber-500/15 text-amber-300 flex items-center justify-center text-[9px] font-bold border border-slate-900">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-white">{{ $winner['item'] }}</p>
                                    <p class="text-[11px] text-white/40 mt-0.5">{{ $winner['category'] }} · {{ $winner['participant_type'] }}</p>
                                    @if($rosterNames->isNotEmpty())
                                        <p class="text-[11px] text-white/60 mt-0.5 uppercase">{{ $rosterNames->take(3)->implode(', ') }}{{ $rosterNames->count() > 3 ? ' +'.($rosterNames->count() - 3) : '' }}</p>
                                    @endif
                                </div>
                                @if(!empty($winner['grade']))
                                    <span class="text-[10px] font-bold text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/30 shrink-0 mt-0.5">
                                        Grade {{ $winner['grade'] }}{{ $winner['grade_points'] !== null ? ' · '.$winner['grade_points'].' pts' : '' }}
                                    </span>
                                @endif
                                <span class="font-mono font-bold text-sm text-white shrink-0 mt-0.5">{{ $winner['points'] }} <small class="text-[10px] text-white/40">PTS</small></span>
                            </li>
                            @endforeach
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 bg-white/5">
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50">Total</span>
                                <span class="font-mono font-extrabold text-base text-amber-400">{{ $row['total_points'] }} <small class="text-[10px] text-white/40">PTS</small></span>
                            </li>
                        </ol>
                    </article>
                    @endforeach
                </div>
                <p id="school-winner-empty" class="hidden rounded-2xl border border-dashed border-slate-700 p-8 text-center text-white/40">No results recorded for that school yet.</p>
            </section>
            <script>
            (() => {
                const picker = document.getElementById('school-winner-picker');
                const cards = [...document.querySelectorAll('[data-school-winner-card]')];
                const empty = document.getElementById('school-winner-empty');
                const section = document.getElementById('school-winners');

                const applyFilter = (schoolId) => {
                    let visible = 0;
                    cards.forEach(card => {
                        const match = !schoolId || card.dataset.schoolId === schoolId;
                        card.hidden = !match;
                        if (match) visible++;
                    });
                    empty.classList.toggle('hidden', visible !== 0);
                };

                picker.addEventListener('change', () => applyFilter(picker.value));

                // School names in the ranking table above jump straight to that school's
                // roster below, instead of making the visitor find it again in the picker.
                document.querySelectorAll('[data-jump-to-school]').forEach(button => {
                    button.addEventListener('click', () => {
                        const schoolId = button.dataset.jumpToSchool;
                        picker.value = schoolId;
                        applyFilter(schoolId);
                        section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });
            })();
            </script>
            @endif
        @elseif($tab === 'category')
            <div class="grid lg:grid-cols-2 gap-5">
                @forelse($categoryBoards as $board)
                    <section class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
                        <div class="px-4 py-3 bg-white/5 border-b border-slate-800">
                            <h2 class="font-bold text-white">{{ $board['label'] }}</h2>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-slate-800">
                                @forelse($board['rows'] as $row)
                                    <tr>
                                        {{-- <td class="p-3 font-bold text-amber-400">#{{ $row['rank'] }}</td> --}}
                                        <td class="p-3 text-white uppercase">{{ $row['school_name'] }}</td>
                                        <td class="p-3 text-right font-mono text-white">{{ $row['total_points'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="p-6 text-center text-white/30">No scores yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                @empty
                    <p class="text-white/40">No categories found.</p>
                @endforelse
            </div>
        @elseif($tab === 'item')
            @php
                $participantTypeLabels = ['pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
                $rankTint = [1 => 'bg-amber-500/10', 2 => 'bg-white/5', 3 => 'bg-orange-500/5'];
            @endphp
            <div class="rounded-2xl border border-slate-800 bg-slate-900/95 backdrop-blur p-3 sm:p-4 grid md:grid-cols-[1fr_auto_auto_auto] gap-3 mb-6 sticky top-32 z-10 shadow-xl">
                <label><span class="sr-only">Search item results</span><input id="result-item-search" type="search" placeholder="Search event item" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white placeholder:text-white/30 text-sm focus:border-amber-500 focus:ring-amber-500"></label>
                <label><span class="sr-only">Filter result category</span><select id="result-item-category" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500"><option value="">All categories</option>@foreach($itemResultsByCategory as $group)<option value="{{ Str::slug($group['key'] ?? 'other') }}">{{ $group['label'] }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter participant type</span><select id="result-item-mode" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500"><option value="">Individual & group</option>@foreach(collect($itemResultsByCategory)->pluck('items')->flatten(1)->pluck('participant_type')->filter()->unique() as $mode)<option value="{{ $mode }}">{{ ucfirst($mode) }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter stage</span><select id="result-item-stage" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500"><option value="">All stages</option><option value="on_stage">On stage</option><option value="off_stage">Off stage</option></select></label>
            </div>
            <p id="result-item-summary" class="text-xs text-white/40 mb-5" aria-live="polite"></p>
            <div class="space-y-10" id="result-item-groups">
                @forelse($itemResultsByCategory as $group)
                    <section data-result-category="{{ Str::slug($group['key'] ?? 'other') }}">
                        <h2 class="text-sm font-bold text-amber-400 uppercase tracking-widest mb-4">{{ $group['label'] }}</h2>
                        <div class="space-y-5">
                            @foreach($group['items'] as $item)
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden" data-result-item data-search="{{ Str::lower(collect([$item['item'], $item['head']])->filter()->implode(' ')) }}" data-mode="{{ $item['participant_type'] ?? 'individual' }}" data-stage="{{ $item['stage_type'] ?? '' }}">
                                    <div class="px-4 py-3 bg-white/5 border-b border-slate-800 flex items-start gap-3">
                                        <div class="min-w-0">
                                            <h3 class="font-bold text-white uppercase">{{ $item['item'] }}</h3>
                                            @if($item['head'])<p class="text-xs text-white/40">{{ $item['head'] }}</p>@endif
                                        </div>
                                        @if($typeLabel = $participantTypeLabels[$item['participant_type'] ?? ''] ?? null)
                                        <span class="shrink-0 text-[11px] font-semibold text-white/60 bg-white/5 border border-slate-700 px-2 py-0.5 rounded-full">{{ $typeLabel }}</span>
                                        @endif
                                        <a href="{{ route('tenant.fest.item-results', [$event->id, $item['item_id']]) }}"
                                           class="ml-auto shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-700 text-white/50 hover:text-white hover:border-slate-500 transition"
                                           title="View full results and points breakdown">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </a>
                                    </div>
                                    <div class="flex flex-wrap">
                                        @foreach($item['winners'] as $winner)
                                        @php
                                            $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
                                        @endphp
                                        <div class="flex gap-3 p-4 flex-1 min-w-[18rem] border-l border-slate-800 first:border-l-0 {{ $rankTint[$winner['position']] ?? '' }}">
                                            <div class="shrink-0 flex flex-col items-center gap-1.5">
                                                @if($winner['position'] <= 3)
                                                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Rank {{ $winner['position'] }}" class="w-14 h-14">
                                                @else
                                                <span class="w-14 h-14 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center text-lg font-extrabold">#{{ $winner['position'] }}</span>
                                                @endif
                                                @if(!empty($winner['grade']))
                                                <span class="text-[11px] font-semibold text-amber-300 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/30 whitespace-nowrap">Grade {{ $winner['grade'] }}</span>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-2.5">
                                                    @foreach($roster as $member)
                                                    <div class="flex flex-col items-center gap-1 w-20">
                                                        @if($member['photo'] ?? null)
                                                        <img src="{{ $member['photo'] }}" alt="" class="w-20 h-20 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                                                        @else
                                                        <span class="w-20 h-20 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center text-lg font-bold border-2 border-slate-700/60 shadow-md shadow-black/30">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                                                        @endif
                                                        <span class="text-[11px] font-semibold leading-tight text-white/90 text-center line-clamp-2 uppercase">{{ $member['name'] ?? '—' }}</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                                <p class="text-xs text-white/40 mt-3 uppercase">{{ $winner['school'] }}</p>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="text-white/40">No item winners published yet.</p>
                @endforelse
            </div>
            <div id="result-item-empty" class="hidden rounded-2xl border border-dashed border-slate-700 p-8 text-center text-white/40">No published item results match those filters.</div>
        @elseif($tab === 'individual')
            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto">
                <div class="px-4 py-3 bg-white/5 border-b border-slate-800">
                    <h2 class="font-bold text-white">Individual Results</h2>
                    <p class="text-xs text-white/40">Every published result, one row per participant — not the same as the Championship tab's cumulative points.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                        <tr><th class="p-3">Participant</th><th class="p-3">School</th><th class="p-3">Item</th><th class="p-3 text-right">Points</th><th class="p-3">Position</th><th class="p-3">Grade</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($individualResults as $row)
                            <tr>
                                <td class="p-3 font-semibold text-white">
                                    <div class="flex items-center gap-2">
                                        @if($row['photo'] ?? null)
                                        <img src="{{ $row['photo'] }}" alt="" class="w-7 h-7 rounded-full object-cover object-top border border-slate-700 shrink-0">
                                        @else
                                        <span class="w-7 h-7 rounded-full bg-amber-500/15 text-amber-300 flex items-center justify-center text-[10px] font-bold shrink-0">{{ strtoupper(substr($row['participant'] ?? '?', 0, 1)) }}</span>
                                        @endif
                                        <span class="uppercase">{{ $row['participant'] }}</span>
                                    </div>
                                </td>
                                <td class="p-3 text-white/70 uppercase">{{ $row['school'] }}</td>
                                <td class="p-3 text-white/70 uppercase">{{ $row['item'] }}</td>
                                <td class="p-3 text-right font-mono font-bold text-white">{{ $row['points'] ?? '—' }}</td>
                                <td class="p-3 font-bold text-white">#{{ $row['position'] }}</td>
                                <td class="p-3 font-semibold text-amber-400">{{ !empty($row['grade']) ? 'Grade '.$row['grade'] : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-white/30">No individual results published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto">
                <div class="px-4 py-3 bg-white/5 border-b border-slate-800">
                    <h2 class="font-bold text-white">Championship Standings</h2>
                    <p class="text-xs text-white/40">Cumulative individual championship points across the whole meet, per student — not the same as the Individual tab's per-result list.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                        <tr><th class="p-3">Rank</th><th class="p-3">Student</th><th class="p-3">School</th><th class="p-3">Category</th><th class="p-3">Gender</th><th class="p-3 text-right">Points</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($championship as $row)
                            <tr>
                                <td class="p-3 font-bold text-amber-400">#{{ $row['rank'] }}</td>
                                <td class="p-3 font-semibold text-white uppercase">{{ $row['student'] }}</td>
                                <td class="p-3 text-white/70 uppercase">{{ $row['school'] }}</td>
                                <td class="p-3 text-white/70">{{ $row['category'] }}</td>
                                <td class="p-3 text-white/70">{{ $row['gender'] }}</td>
                                <td class="p-3 text-right font-mono text-white">{{ $row['points'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-white/30">No championship points published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@if($tab === 'item')
<script>
(() => {
    const search = document.getElementById('result-item-search');
    const category = document.getElementById('result-item-category');
    const mode = document.getElementById('result-item-mode');
    const stage = document.getElementById('result-item-stage');
    const items = [...document.querySelectorAll('[data-result-item]')];
    const groups = [...document.querySelectorAll('[data-result-category]')];
    const summary = document.getElementById('result-item-summary');
    const empty = document.getElementById('result-item-empty');
    const apply = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let count = 0;
        items.forEach(item => {
            const group = item.closest('[data-result-category]');
            const visible = (!query || item.dataset.search.includes(query)) && (!category.value || group.dataset.resultCategory === category.value) && (!mode.value || item.dataset.mode === mode.value) && (!stage.value || item.dataset.stage === stage.value);
            item.hidden = !visible;
            if (visible) count++;
        });
        groups.forEach(group => group.hidden = !group.querySelector('[data-result-item]:not([hidden])'));
        summary.textContent = `Showing ${count} published ${count === 1 ? 'item result' : 'item results'}`;
        empty.classList.toggle('hidden', count !== 0);
    };
    [search, category, mode, stage].forEach(control => control.addEventListener(control === search ? 'input' : 'change', apply));
    apply();
})();
</script>
@endif
@endsection
