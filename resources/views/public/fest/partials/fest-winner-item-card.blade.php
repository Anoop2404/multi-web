<article class="rounded-2xl bg-slate-900 border border-slate-800 shadow-md overflow-hidden">
    <div class="px-4 py-2.5 bg-white/5 border-b border-slate-800">
        <p class="font-bold text-white text-sm truncate">{{ $itemGroup['item'] }}</p>
        @if($itemGroup['head'])<p class="text-[11px] text-white/40 truncate">{{ $itemGroup['head'] }}</p>@endif
    </div>
    {{-- flex-wrap, not divide-y: multiple awarded positions for the same item sit side by
         side in one row (wrapping only if there's genuinely no room), instead of stacking
         each position's whole block underneath the last. --}}
    <div class="flex flex-wrap">
        @foreach($itemGroup['winners'] as $winner)
        @php
            $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
            // The "+K more" tile below takes one of the $rosterLimit slots itself, so a
            // truncated roster shows rosterLimit-1 real members + 1 indicator — exactly
            // rosterLimit tiles total, not rosterLimit+1 (which would silently wrap to a
            // second row and defeat the whole point of capping).
            $hiddenCount = (isset($rosterLimit) && count($roster) > $rosterLimit) ? count($roster) - ($rosterLimit - 1) : 0;
            $visibleRoster = $hiddenCount ? array_slice($roster, 0, $rosterLimit - 1) : $roster;
        @endphp
        <div class="flex gap-3 p-4 flex-1 min-w-[18rem] border-l border-slate-800 first:border-l-0">
            <div class="shrink-0">
                @if($winner['position'] <= 3)
                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Position {{ $winner['position'] }}" class="w-14 h-14">
                @else
                <span class="w-14 h-14 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center text-lg font-extrabold" aria-label="Position {{ $winner['position'] }}">#{{ $winner['position'] }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-2.5">
                    @foreach($visibleRoster as $member)
                    <div class="flex flex-col items-center gap-1 w-20">
                        @if($member['photo'] ?? null)
                        <img src="{{ $member['photo'] }}" alt="" class="w-20 h-20 rounded-xl object-cover border-2 border-slate-700/60 shadow-md shadow-black/30">
                        @else
                        <span class="w-20 h-20 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-lg border-2 border-slate-700/60 shadow-md shadow-black/30">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                        <span class="text-[11px] font-semibold leading-tight text-white/90 text-center line-clamp-2">{{ $member['name'] ?? '—' }}</span>
                    </div>
                    @endforeach
                    @if($hiddenCount)
                    <div class="flex flex-col items-center gap-1 w-20">
                        <span class="w-20 h-20 rounded-xl bg-slate-800/60 border-2 border-dashed border-slate-700 flex items-center justify-center text-slate-300 font-extrabold text-base">+{{ $hiddenCount }}</span>
                        <span class="text-[11px] font-semibold leading-tight text-white/50 text-center">more</span>
                    </div>
                    @endif
                </div>
                <p class="text-xs text-slate-400 mt-3 truncate">{{ $winner['school'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</article>
