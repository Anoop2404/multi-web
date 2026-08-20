@php
    $badges = $badges ?? [];
@endphp
<header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-950 to-slate-900 border border-amber-500/20 p-6 sm:p-8 shadow-2xl">
    <div aria-hidden="true" class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-amber-500/15 blur-3xl"></div>
    <div aria-hidden="true" class="absolute -bottom-24 left-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
    <div class="relative">
        <p class="text-xs font-bold uppercase tracking-widest text-amber-400">{{ $eyebrow }}</p>
        <h1 class="text-2xl sm:text-3xl font-extrabold font-heading text-white mt-1 leading-tight">{{ $title }}</h1>
        @isset($subtitle)
        <p class="text-white/50 text-sm mt-2">{{ $subtitle }}</p>
        @endisset
        @if(count($badges))
        <div class="flex flex-wrap gap-2 mt-4 text-[11px] font-bold uppercase tracking-wide">
            @foreach($badges as $badge)
            <span class="rounded-full border border-white/15 bg-white/5 text-white/70 px-2.5 py-1">{{ $badge }}</span>
            @endforeach
        </div>
        @endif
        @isset($meta)
        <p class="text-xs text-white/30 mt-3">{{ $meta }}</p>
        @endisset
    </div>
</header>
