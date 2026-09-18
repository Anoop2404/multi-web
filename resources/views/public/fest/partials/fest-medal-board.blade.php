<div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden">
    <div class="grid grid-cols-[5rem_1fr_repeat(4,5rem)_8rem] gap-3 px-6 py-3 bg-white/5 border-b border-slate-800 text-base font-extrabold uppercase tracking-wider text-slate-400">
        <span>Rank</span>
        <span>School</span>
        {{-- Points earned from that rank specifically (see FestPortalController::tv()'s
             $medalTallyFor), not a count of how many times the school placed there. --}}
        <span class="flex flex-col items-center justify-center gap-1"><img src="{{ asset('images/fest/medals/rank-1.webp') }}" alt="Points from 1st place" class="w-9 h-9"><span class="normal-case text-xs font-semibold tracking-normal text-slate-500">pts</span></span>
        <span class="flex flex-col items-center justify-center gap-1"><img src="{{ asset('images/fest/medals/rank-2.webp') }}" alt="Points from 2nd place" class="w-9 h-9"><span class="normal-case text-xs font-semibold tracking-normal text-slate-500">pts</span></span>
        <span class="flex flex-col items-center justify-center gap-1"><img src="{{ asset('images/fest/medals/rank-3.webp') }}" alt="Points from 3rd place" class="w-9 h-9"><span class="normal-case text-xs font-semibold tracking-normal text-slate-500">pts</span></span>
        {{-- Grade points earned off the podium (e.g. Grade A with no 1st/2nd/3rd finish) —
             without this column Total Points had no visible source when gold/silver/bronze
             were all zero. --}}
        <span class="flex flex-col items-center justify-center gap-1"><span>Grade</span><span class="normal-case text-xs font-semibold tracking-normal text-slate-500">pts</span></span>
        <span class="text-right">Total Points</span>
    </div>
    <div class="divide-y divide-slate-800/80">
        @php $showMedalRank = $showMedalRank ?? true; @endphp
        @forelse($rows as $row)
        @php
            $rankClass = $showMedalRank ? match((int) $row['rank']) {
                1 => 'bg-gradient-to-r from-amber-500/10 to-transparent',
                2 => 'bg-gradient-to-r from-slate-400/10 to-transparent',
                3 => 'bg-gradient-to-r from-amber-700/10 to-transparent',
                default => '',
            } : '';
        @endphp
        <div class="grid grid-cols-[5rem_1fr_repeat(4,5rem)_8rem] gap-3 items-center px-6 py-4 {{ $rankClass }}">
            <span class="flex items-center">
                {{-- Medal icons imply an actual result — only show them once there's a real
                     ranking. A pre-results roster (everyone at 0) uses plain numbers even
                     for rows 1-3, so it can't be misread as "already won something." --}}
                @if($showMedalRank && $row['rank'] <= 3)
                <img src="{{ asset('images/fest/medals/rank-'.$row['rank'].'.webp') }}" alt="Rank {{ $row['rank'] }}" class="w-12 h-12">
                @else
                <span class="w-12 h-12 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center text-xl font-extrabold">{{ $row['rank'] }}</span>
                @endif
            </span>
            <span class="font-black text-white text-2xl uppercase truncate" title="{{ $row['school_name'] }}">{{ $row['school_name'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-amber-300 text-xl">{{ $row['gold'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-slate-300 text-xl">{{ $row['silver'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-amber-600 text-xl">{{ $row['bronze'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-sky-300 text-xl">{{ $row['grade_points'] ?? 0 }}</span>
            <span class="text-right font-mono font-extrabold tabular-nums text-amber-400 text-3xl">{{ $row['total_points'] }}</span>
        </div>
        @empty
        <div class="text-slate-400 text-center py-12 text-xl">No standings yet.</div>
        @endforelse
    </div>
</div>
