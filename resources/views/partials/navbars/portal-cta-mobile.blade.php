{{-- Mobile menu Login button (portal landing) --}}
@php
    $cta = $navConfig['portal_cta'] ?? [];
    $show = $cta['show_in_navbar'] ?? false;
    $url = $cta['portal_url'] ?? $cta['login_url'] ?? '/portal';
    $label = $cta['portal_label'] ?? $cta['login_label'] ?? 'Login';
@endphp
@if($show)
<div class="pt-3 mt-2 border-t border-gray-100 space-y-2 lg:hidden">
    <a href="{{ $url }}"
       class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold text-white"
       style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
        {{ $label }}
    </a>
</div>
@endif
