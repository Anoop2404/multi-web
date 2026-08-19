@extends('layouts.public')

@section('content')
@php
    $tabs = [
        'school' => 'School-wise',
        'category' => 'Category-wise',
        'item' => 'Item-wise',
        'individual' => 'Individual',
        'championship' => 'Championship',
    ];
@endphp

<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Published Results</p>
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold font-heading">{{ $event->title }}</h1>
                <p class="text-gray-500 text-sm mt-1">Browse results by school, category, item, or participant.</p>
                @if($publishedAt ?? null)
                    <p class="text-xs text-gray-400 mt-1">Results published on {{ \Carbon\Carbon::parse($publishedAt)->format('d M Y, g:i A') }}</p>
                @endif
            </div>
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-sm font-semibold text-amber-700 hover:underline">Scoreboard →</a>
        </div>

        @include('public.fest.partials.scope-nav', [
            'routeName' => 'tenant.fest.results',
            'routeQuery' => ['tab' => $tab],
        ])

        <p class="text-sm font-semibold text-gray-600 mb-4">{{ $selectedScope['label'] }}</p>

        <nav class="flex flex-wrap gap-2 mb-8">
            @foreach($tabs as $key => $label)
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'scope' => $selectedScope['key'], 'tab' => $key]) }}"
                   class="px-4 py-2 rounded-full text-sm border {{ $tab === $key ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:border-amber-400' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if($tab === 'school')
            {{-- §7.3a (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): phased events show
                 the cumulative overall (sum of every published phase's points, revealed
                 progressively as phases publish) instead of the plain school board.
                 $phaseCumulativeBoard is null for every non-phased event and for any
                 non-'overall' scope — falls straight through to the unchanged table below,
                 exactly today's display. --}}
            @if(($phaseCumulativeBoard ?? null) !== null)
                <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-800">Cumulative overall standing</p>
                    <p class="text-xs text-amber-700 mt-1">
                        Running total across published phases so far —
                        @if(count($phaseBreakdown ?? []))
                            {{ collect($phaseBreakdown)->map(fn ($p) => $p['name'].($p['results_published'] ? '' : ' (not yet published)'))->implode(', ') }}.
                        @else
                            no phases published yet.
                        @endif
                    </p>
                </div>
                <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="p-3">Rank</th>
                                <th class="p-3">School</th>
                                @foreach(collect($phaseBreakdown ?? [])->where('results_published', true) as $phase)
                                    <th class="p-3 text-right">{{ $phase['name'] }}</th>
                                @endforeach
                                <th class="p-3 text-right">Cumulative Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($phaseCumulativeBoard as $row)
                                <tr class="border-t">
                                    <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                    <td class="p-3 font-semibold">{{ $row['school_name'] }}</td>
                                    @foreach(collect($phaseBreakdown ?? [])->where('results_published', true) as $phase)
                                        <td class="p-3 text-right font-mono">{{ $row['phase_points'][$phase['phase_id']] ?? 0 }}</td>
                                    @endforeach
                                    <td class="p-3 text-right font-mono">{{ $row['total_points'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 3 + collect($phaseBreakdown ?? [])->where('results_published', true)->count() }}" class="p-8 text-center text-gray-400">No phase results published yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
            <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Rank</th>
                            <th class="p-3">School</th>
                            <th class="p-3 text-center" title="Gold — 1st place finishes">🥇</th>
                            <th class="p-3 text-center" title="Silver — 2nd place finishes">🥈</th>
                            <th class="p-3 text-center" title="Bronze — 3rd place finishes">🥉</th>
                            <th class="p-3 text-right">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schoolBoard as $row)
                            <tr class="border-t">
                                <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                <td class="p-3 font-semibold">{{ $row['school_name'] }}</td>
                                <td class="p-3 text-center font-mono {{ $row['gold'] ? 'font-bold text-amber-600' : 'text-gray-300' }}">{{ $row['gold'] }}</td>
                                <td class="p-3 text-center font-mono {{ $row['silver'] ? 'font-bold text-slate-500' : 'text-gray-300' }}">{{ $row['silver'] }}</td>
                                <td class="p-3 text-center font-mono {{ $row['bronze'] ? 'font-bold text-amber-800' : 'text-gray-300' }}">{{ $row['bronze'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['total_points'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-gray-400">No school points published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @endif
        @elseif($tab === 'category')
            <div class="grid lg:grid-cols-2 gap-5">
                @forelse($categoryBoards as $board)
                    <section class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                        <div class="px-4 py-3 bg-gray-50 border-b">
                            <h2 class="font-bold">{{ $board['label'] }}</h2>
                        </div>
                        <table class="w-full text-sm">
                            <tbody>
                                @forelse($board['rows'] as $row)
                                    <tr class="border-t">
                                        <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                        <td class="p-3">{{ $row['school_name'] }}</td>
                                        <td class="p-3 text-right font-mono">{{ $row['total_points'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="p-6 text-center text-gray-400">No scores yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                @empty
                    <p class="text-gray-400">No categories found.</p>
                @endforelse
            </div>
        @elseif($tab === 'item')
            @php
                $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                $participantTypeLabels = ['pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
                $rankTint = [1 => 'bg-amber-50/70', 2 => 'bg-slate-50/70', 3 => 'bg-orange-50/40'];
            @endphp
            <div class="space-y-10">
                @forelse($itemResultsByCategory as $group)
                    <section>
                        <h2 class="text-sm font-bold text-accent uppercase tracking-widest mb-4">{{ $group['label'] }}</h2>
                        <div class="space-y-5">
                            @foreach($group['items'] as $item)
                                <div class="v2-card overflow-hidden">
                                    <div class="px-4 py-3 bg-gray-50 border-b flex items-start gap-3">
                                        <div class="min-w-0">
                                            <h3 class="font-bold">{{ $item['item'] }}</h3>
                                            @if($item['head'])<p class="text-xs text-gray-500">{{ $item['head'] }}</p>@endif
                                        </div>
                                        @if($typeLabel = $participantTypeLabels[$item['participant_type'] ?? ''] ?? null)
                                        <span class="ml-auto shrink-0 text-[11px] font-semibold text-gray-500 bg-white border px-2 py-0.5 rounded-full">{{ $typeLabel }}</span>
                                        @endif
                                    </div>
                                    <div class="grid sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x">
                                        @foreach($item['winners'] as $winner)
                                        @php
                                            $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
                                            $visibleRoster = array_slice($roster, 0, 4);
                                        @endphp
                                        <div class="p-4 flex flex-col items-center text-center gap-2 {{ $rankTint[$winner['position']] ?? '' }}">
                                            <span class="text-2xl leading-none">{{ $medals[$winner['position']] ?? '#'.$winner['position'] }}</span>

                                            <div class="flex -space-x-3">
                                                @foreach($visibleRoster as $member)
                                                    @if($member['photo'] ?? null)
                                                    <img src="{{ $member['photo'] }}" alt="" class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm">
                                                    @else
                                                    <span class="w-12 h-12 rounded-full v2-badge-accent flex items-center justify-center text-sm font-bold border-2 border-white shadow-sm">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                                                    @endif
                                                @endforeach
                                                @if(count($roster) > 4)
                                                <span class="w-12 h-12 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-xs font-bold border-2 border-white shadow-sm">+{{ count($roster) - 4 }}</span>
                                                @endif
                                            </div>

                                            <div>
                                                <p class="font-semibold text-sm leading-snug">
                                                    {{ collect($roster)->pluck('name')->filter()->implode(', ') ?: '—' }}
                                                </p>
                                                <p class="text-xs text-gray-500 mt-0.5 truncate max-w-[16rem]">{{ $winner['school'] }}</p>
                                            </div>

                                            @if(!empty($winner['grade']))
                                            <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">Grade {{ $winner['grade'] }}</span>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="text-gray-400">No item winners published yet.</p>
                @endforelse
            </div>
        @elseif($tab === 'individual')
            <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="p-3">Participant</th><th class="p-3">School</th><th class="p-3">Item</th><th class="p-3">Position</th><th class="p-3">Grade</th></tr>
                    </thead>
                    <tbody>
                        @forelse($individualResults as $row)
                            <tr class="border-t">
                                <td class="p-3 font-semibold">
                                    <div class="flex items-center gap-2">
                                        @if($row['photo'] ?? null)
                                        <img src="{{ $row['photo'] }}" alt="" class="w-7 h-7 rounded-full object-cover border shrink-0">
                                        @else
                                        <span class="w-7 h-7 rounded-full v2-badge-primary flex items-center justify-center text-[10px] font-bold shrink-0">{{ strtoupper(substr($row['participant'] ?? '?', 0, 1)) }}</span>
                                        @endif
                                        {{ $row['participant'] }}
                                    </div>
                                </td>
                                <td class="p-3">{{ $row['school'] }}</td>
                                <td class="p-3">{{ $row['item'] }}</td>
                                <td class="p-3 font-bold">#{{ $row['position'] }}</td>
                                <td class="p-3 font-semibold text-amber-700">{{ !empty($row['grade']) ? 'Grade '.$row['grade'] : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-400">No individual results published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h2 class="font-bold">Individual Championship</h2>
                    <p class="text-xs text-gray-500">Total points across the whole meet, per student.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="p-3">Rank</th><th class="p-3">Student</th><th class="p-3">School</th><th class="p-3">Category</th><th class="p-3">Gender</th><th class="p-3 text-right">Points</th></tr>
                    </thead>
                    <tbody>
                        @forelse($championship as $row)
                            <tr class="border-t">
                                <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                <td class="p-3 font-semibold">{{ $row['student'] }}</td>
                                <td class="p-3">{{ $row['school'] }}</td>
                                <td class="p-3">{{ $row['category'] }}</td>
                                <td class="p-3">{{ $row['gender'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['points'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-gray-400">No championship points published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection
