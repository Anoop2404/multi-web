<div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden">
    <div class="grid grid-cols-[3.5rem_1fr_repeat(3,4rem)_6rem] gap-2 px-5 py-3 bg-white/5 border-b border-slate-800 text-xs font-extrabold uppercase tracking-wider text-slate-400">
        <span>Rank</span>
        <span>School</span>
        <span class="flex items-center justify-center"><img src="{{ asset('images/fest/medals/rank-1.webp') }}" alt="Gold count" class="w-5 h-5"></span>
        <span class="flex items-center justify-center"><img src="{{ asset('images/fest/medals/rank-2.webp') }}" alt="Silver count" class="w-5 h-5"></span>
        <span class="flex items-center justify-center"><img src="{{ asset('images/fest/medals/rank-3.webp') }}" alt="Bronze count" class="w-5 h-5"></span>
        <span class="text-right">Points</span>
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
        <div class="grid grid-cols-[3.5rem_1fr_repeat(3,4rem)_6rem] gap-2 items-center px-5 py-3 {{ $rankClass }}">
            <span class="flex items-center">
                {{-- Medal icons imply an actual result — only show them once there's a real
                     ranking. A pre-results roster (everyone at 0) uses plain numbers even
                     for rows 1-3, so it can't be misread as "already won something." --}}
                @if($showMedalRank && $row['rank'] <= 3)
                <img src="{{ asset('images/fest/medals/rank-'.$row['rank'].'.webp') }}" alt="Rank {{ $row['rank'] }}" class="w-8 h-8">
                @else
                <span class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center text-sm font-extrabold">{{ $row['rank'] }}</span>
                @endif
            </span>
            <span class="font-bold text-white text-base uppercase">{{ $row['school_name'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-amber-300 text-base">{{ $row['gold'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-slate-300 text-base">{{ $row['silver'] }}</span>
            <span class="text-center font-mono font-bold tabular-nums text-amber-600 text-base">{{ $row['bronze'] }}</span>
            <span class="text-right font-mono font-extrabold tabular-nums text-amber-400 text-lg">{{ $row['total_points'] }}</span>
        </div>
        @empty
        <div class="text-slate-400 text-center py-12">No standings yet.</div>
        @endforelse
    </div>
</div>
