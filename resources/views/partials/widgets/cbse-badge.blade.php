@php
    $affNo = $widgets['cbse_affiliation_number'] ?? null;
    $show  = $widgets['cbse_badge_show'] ?? true;
@endphp
@if($show)
<div class="fixed right-0 top-1/2 -translate-y-1/2 z-50">
    <a href="/disclosure"
       class="flex flex-col items-center gap-1.5 text-white shadow-lg px-2.5 py-4 rounded-l-xl hover:pr-3.5 transition-all"
       style="background: linear-gradient(180deg, var(--color-primary) 0%, var(--color-secondary, var(--color-primary)) 100%)"
       aria-label="CBSE Affiliated — view mandatory public disclosure">
        <span class="font-extrabold text-sm tracking-wide leading-none [writing-mode:vertical-rl] rotate-180">CBSE AFFILIATED</span>
        @if($affNo)
        <span class="text-[10px] font-semibold text-white/80 [writing-mode:vertical-rl] rotate-180">No. {{ $affNo }}</span>
        @endif
    </a>
</div>
@endif
