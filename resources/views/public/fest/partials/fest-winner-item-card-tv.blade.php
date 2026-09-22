{{-- TV-only variant of fest-winner-item-card.blade.php — sized for a 1920x1080 screen
     viewed from several meters away, not a desktop widget. Kept as its own file rather
     than a shared one with size flags so the regular scoreboard page's card is never
     affected by TV-specific sizing changes. --}}
{{-- data-title/data-category/data-gender/data-participant-type: a squad item's card
     can itself run taller than the viewport (a large team's roster wraps to several
     rows) — tv.blade.php's sticky label reads these to keep naming which item's
     winners are on screen, with the same category/gender/type badges below, even
     while scrolled deep inside this one card. --}}
<article data-tv-winner-item data-title="{{ $itemGroup['item'] }}" data-category="{{ $itemGroup['category_label'] ?? '' }}" data-gender="{{ $itemGroup['gender_label'] ?? '' }}" data-participant-type="{{ $itemGroup['participant_type'] ?? '' }}" class="rounded-2xl bg-slate-900 border border-slate-800 shadow-md overflow-hidden">
    <div class="px-6 py-4 bg-white/5 border-b border-slate-800">
        <div class="flex items-start justify-between gap-4">
            <p class="font-bold text-white text-2xl uppercase">{{ $itemGroup['item'] }}</p>
        </div>
        {{-- Category + gender + individual/group disambiguate items that share the
             same title across different categories/genders/modes (e.g. "Extempore -
             English" run separately for Category 1 Boys and Category 3 Girls) and
             tell a viewer at a glance whether this is a solo or team result. --}}
        @if(($itemGroup['category_label'] ?? null) || ($itemGroup['gender_label'] ?? null) || ($itemGroup['participant_type'] ?? null))
        <div class="flex flex-wrap gap-1.5 mt-2">
            @if($itemGroup['category_label'] ?? null)
            <span class="rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-300 text-xs font-bold uppercase tracking-wide px-2.5 py-1">{{ $itemGroup['category_label'] }}</span>
            @endif
            @if($itemGroup['gender_label'] ?? null)
            <span class="rounded-full border border-slate-700 bg-white/5 text-slate-300 text-xs font-bold uppercase tracking-wide px-2.5 py-1">{{ $itemGroup['gender_label'] }}</span>
            @endif
            @if($itemGroup['participant_type'] ?? null)
            <span class="rounded-full border border-sky-500/30 bg-sky-500/10 text-sky-300 text-xs font-bold uppercase tracking-wide px-2.5 py-1">{{ $itemGroup['participant_type'] }}</span>
            @endif
        </div>
        @endif
        @if($itemGroup['head'])<p class="text-sm text-white/40 mt-1.5">{{ $itemGroup['head'] }}</p>@endif
    </div>
    {{-- flex-wrap, not divide-y: multiple awarded positions for the same item sit side by
         side in one row, wrapping to further rows only when there's genuinely no room —
         a large team's own roster grid below wraps the same way, so nothing here needs
         to be pre-split by the controller. --}}
    <div class="flex flex-wrap">
        @foreach($itemGroup['winners'] as $winner)
        @php
            $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null, 'photo_fallback' => $winner['photo_fallback'] ?? null]];
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
                     side, that track can end up narrower than a tile — auto-fill instead
                     always gives every column at least minmax's floor, reducing the
                     column COUNT (wrapping to more rows) rather than shrinking columns
                     below it.
                     That floor MUST be in the same unit (rem) as the tile's own w-24
                     below, not a hardcoded px value — this page's root font-size is
                     scaled up for venue-distance legibility (see layouts/public-event-
                     tv.blade.php), so w-24 (6rem) renders at 156px here, not the 96px a
                     plain "96px" floor assumed. A px floor smaller than the tile's real
                     rem-based width let tiles render wider than their own grid track,
                     visually spilling each name into the next tile's space (rendered as
                     "INIKA JOEEPHERTEENA" on screen) — the grid math itself was correct,
                     the two sizes just disagreed once root font-size entered the
                     picture. --}}
                <div class="grid grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] gap-3">
                    @foreach($roster as $member)
                    <div class="flex flex-col items-center gap-1.5 w-24 min-w-0">
                        @if($member['photo'] ?? null)
                        <img src="{{ $member['photo'] }}" @if($member['photo_fallback'] ?? null) data-fallback-src="{{ $member['photo_fallback'] }}" onerror="this.onerror=null;this.src=this.dataset.fallbackSrc" @endif loading="lazy" decoding="async" alt="" class="w-24 h-24 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                        @else
                        <span class="w-24 h-24 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-2xl border-2 border-slate-700/60 shadow-md shadow-black/30">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                        <span class="text-base font-semibold leading-tight text-white/90 text-center uppercase break-words w-full">{{ $member['name'] ?? '—' }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="flex items-center gap-3 mt-3">
                    <p class="text-base text-slate-400 uppercase">{{ $winner['school'] }}</p>
                    {{-- roster_total is only set for a squad/team item's winner (see tv()) —
                         null for an individual item, where a member count adds nothing. --}}
                    @if(($winner['roster_total'] ?? null) > 1)
                    <span class="shrink-0 rounded-full bg-slate-800 border border-slate-700 text-slate-300 text-sm font-bold px-3 py-1">{{ $winner['roster_total'] }} members</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</article>
