<article class="rounded-2xl bg-slate-900 border border-slate-800 shadow-md overflow-hidden">
    <div class="px-8 py-5 bg-white/5 border-b border-slate-800">
        <p class="font-bold text-white text-4xl uppercase">{{ $itemGroup['item'] }}</p>
        {{-- Category + gender disambiguate items that share the same title across
             different categories/genders (e.g. "Extempore - English" run separately for
             Category 1 Boys and Category 3 Girls). --}}
        @if(($itemGroup['category_label'] ?? null) || ($itemGroup['gender_label'] ?? null))
        <p class="text-xl text-amber-300/80 font-semibold mt-1">{{ collect([$itemGroup['category_label'] ?? null, $itemGroup['gender_label'] ?? null])->filter()->implode(' · ') }}</p>
        @endif
        @if($itemGroup['head'])<p class="text-lg text-white/40">{{ $itemGroup['head'] }}</p>@endif
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
        <div class="flex gap-5 p-6 flex-1 min-w-[26rem] border-l border-slate-800 first:border-l-0">
            <div class="shrink-0">
                @if($winner['position'] <= 3)
                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Position {{ $winner['position'] }}" class="w-28 h-28">
                @else
                <span class="w-28 h-28 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center text-4xl font-extrabold" aria-label="Position {{ $winner['position'] }}">#{{ $winner['position'] }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-4">
                    @foreach($visibleRoster as $member)
                    <div class="flex flex-col items-center gap-2 w-36">
                        @if($member['photo'] ?? null)
                        <img src="{{ $member['photo'] }}" alt="" class="w-36 h-36 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                        @else
                        <span class="w-36 h-36 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-4xl border-2 border-slate-700/60 shadow-md shadow-black/30">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                        <span class="text-lg font-semibold leading-tight text-white/90 text-center uppercase break-words">{{ $member['name'] ?? '—' }}</span>
                    </div>
                    @endforeach
                    @if($hiddenCount)
                    <div class="flex flex-col items-center gap-2 w-36">
                        <span class="w-36 h-36 rounded-xl bg-slate-800/60 border-2 border-dashed border-slate-700 flex items-center justify-center text-slate-300 font-extrabold text-3xl">+{{ $hiddenCount }}</span>
                        <span class="text-lg font-semibold leading-tight text-white/50 text-center">more</span>
                    </div>
                    @endif
                </div>
                <p class="text-xl text-slate-400 mt-4 uppercase">{{ $winner['school'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</article>
