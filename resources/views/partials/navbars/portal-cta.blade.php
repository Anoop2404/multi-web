{{-- Single navbar Login button → portal landing with register + login options --}}
@php
    $cta = $navConfig['portal_cta'] ?? [];
    $show = $cta['show_in_navbar'] ?? false;
    $url = $cta['portal_url'] ?? $cta['login_url'] ?? '/portal';
    $label = $cta['portal_label'] ?? $cta['login_label'] ?? 'Login';
@endphp
@if($show)
<div class="hidden lg:flex items-center shrink-0">
    <a href="{{ $url }}"
       class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold text-white shadow-sm hover:opacity-90 transition"
       style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
        {{ $label }}
    </a>
</div>
@endif
