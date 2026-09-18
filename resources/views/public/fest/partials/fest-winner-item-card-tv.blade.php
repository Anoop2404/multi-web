{{-- TV-only variant of fest-winner-item-card.blade.php — sized for a 1920x1080 screen
     viewed from several meters away, not a desktop widget. Kept as its own file rather
     than a shared one with size flags so the regular scoreboard page's card is never
     affected by TV-specific sizing changes. --}}
<article class="rounded-2xl bg-slate-900 border border-slate-800 shadow-md overflow-hidden">
    <div class="px-6 py-4 bg-white/5 border-b border-slate-800">
        <div class="flex items-start justify-between gap-4">
            <p class="font-bold text-white text-2xl uppercase">{{ $itemGroup['item'] }}</p>
            {{-- Set by FestPortalController::tv() when a large team roster and/or
                 multiple winning positions needed more than one slide for this item —
                 without this, a viewer has no way to tell two consecutive slides
                 sharing a title are part of the same result rather than a coincidence. --}}
            @if(($itemGroup['split_total'] ?? 1) > 1)
            <span class="shrink-0 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-sm font-bold uppercase tracking-wide px-3 py-1">Result {{ $itemGroup['split_position'] }} of {{ $itemGroup['split_total'] }}</span>
            @endif
        </div>
        {{-- Category + gender disambiguate items that share the same title across
             different categories/genders (e.g. "Extempore - English" run separately for
             Category 1 Boys and Category 3 Girls). --}}
        @if(($itemGroup['category_label'] ?? null) || ($itemGroup['gender_label'] ?? null))
        <p class="text-base text-amber-300/80 font-semibold mt-1">{{ collect([$itemGroup['category_label'] ?? null, $itemGroup['gender_label'] ?? null])->filter()->implode(' · ') }}</p>
        @endif
        @if($itemGroup['head'])<p class="text-sm text-white/40 mt-0.5">{{ $itemGroup['head'] }}</p>@endif
    </div>
    {{-- flex-wrap, not divide-y: multiple awarded positions for the same item sit side by
         side in one row (wrapping only if there's genuinely no room), instead of stacking
         each position's whole block underneath the last. In practice this only ever
         renders one winner per slide once split_total > 1 — tv() has already broken a
         multi-position or oversized-roster item into separate slides by then — but stays
         multi-capable so a 2-3 position item whose rosters are small still renders
         exactly as it always has, side by side on one slide. --}}
    <div class="flex flex-wrap">
        @foreach($itemGroup['winners'] as $winner)
        @php
            // tv() pre-chunks any roster larger than its own per-slide cap before this
            // partial ever sees it, so $roster here is always small enough to render in
            // full — no "+N more" truncation tile needed.
            $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
        @endphp
        <div class="flex gap-4 p-5 flex-1 min-w-[22rem] border-l border-slate-800 first:border-l-0">
            <div class="shrink-0">
                @if($winner['position'] <= 3)
                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Position {{ $winner['position'] }}" class="w-16 h-16">
                @else
                <span class="w-16 h-16 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center text-xl font-extrabold" aria-label="Position {{ $winner['position'] }}">#{{ $winner['position'] }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                {{-- auto-fill, not a fixed column count: a fixed grid-cols-4 computes each
                     track as (available width / 4) regardless of how narrow that leaves
                     it — with 3 winning positions (each its own team) squeezed side by
                     side, that track can end up narrower than a tile's own fixed w-24,
                     so tiles/names overflow their track and overlap the next one into an
                     unreadable mess. auto-fill instead always gives every column at least
                     minmax's 96px floor, reducing the column COUNT (wrapping to more
                     rows) rather than shrinking columns below that floor — it can never
                     overlap, only take more vertical space. --}}
                <div class="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-3">
                    @foreach($roster as $member)
                    <div class="flex flex-col items-center gap-1.5 w-24">
                        @if($member['photo'] ?? null)
                        <img src="{{ $member['photo'] }}" alt="" class="w-24 h-24 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                        @else
                        <span class="w-24 h-24 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-2xl border-2 border-slate-700/60 shadow-md shadow-black/30">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                        <span class="text-base font-semibold leading-tight text-white/90 text-center uppercase break-words">{{ $member['name'] ?? '—' }}</span>
                    </div>
                    @endforeach
                </div>
                <p class="text-base text-slate-400 mt-3 uppercase">{{ $winner['school'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</article>
