@php
    $cta = $navConfig['portal_cta'] ?? [];
    $show = $cta['show_in_navbar'] ?? false;
    $url = $cta['portal_url'] ?? $cta['login_url'] ?? '/login';
    $label = $cta['portal_label'] ?? $cta['login_label'] ?? 'School Login';
    $isSchool = isset($tenant) && $tenant->type === 'school';
    $showCbse = $cta['cbse_btn'] ?? $isSchool;
    $cbseUrl = $cta['cbse_url'] ?? '/disclosure';
    $showContact = $cta['contact_btn'] ?? $isSchool;
    $contactUrl = $cta['contact_url'] ?? '/contact';
@endphp
<div class="hidden lg:flex items-center gap-2.5 shrink-0 ml-3">
    @if($showCbse)
    <a href="{{ $cbseUrl }}"
       class="inline-flex items-center justify-center px-3.5 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-wider text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all">
        CBSE
    </a>
    @endif

    @if($showContact)
    <a href="{{ $contactUrl }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-all">
        <span>Contact Us</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </a>
    @endif

    @if($show)
    <a href="{{ $url }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap shadow-sm hover:shadow transition-all bg-amber-500 hover:bg-amber-400 text-slate-950">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25V9m12 8.25v-1.5a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 15.75v1.5m18 0A2.25 2.25 0 0118.75 19.5H5.25A2.25 2.25 0 013 17.25m18 0V9.75A2.25 2.25 0 0018.75 7.5H5.25A2.25 2.25 0 003 9.75v7.5"/></svg>
        <span>{{ $label }}</span>
    </a>
    @endif
</div>
