{{-- Mobile menu Login button (portal landing) --}}
@php
    $cta = $navConfig['portal_cta'] ?? [];
    $show = $cta['show_in_navbar'] ?? false;
    $url = $cta['portal_url'] ?? $cta['login_url'] ?? '/portal';
    $label = $cta['portal_label'] ?? $cta['login_label'] ?? 'Login';
    $isSchool = isset($tenant) && $tenant->type === 'school';
    $showCbse = $cta['cbse_btn'] ?? $isSchool;
    $cbseUrl = $cta['cbse_url'] ?? '/disclosure';
    $cbseLabel = $cta['cbse_label'] ?? 'CBSE';
    $showContact = $cta['contact_btn'] ?? $isSchool;
    $contactUrl = $cta['contact_url'] ?? '/contact';
    $contactLabel = $cta['contact_label'] ?? 'Contact Us';
@endphp
@if($show || $showCbse || $showContact)
<div class="pt-3 mt-2 border-t border-gray-100 space-y-2 xl:hidden">
    <div class="grid grid-cols-2 gap-2">
        @if($showCbse)
        <a href="{{ $cbseUrl }}" class="block text-center px-3 py-2.5 rounded-xl text-sm font-bold text-white" style="background-color: var(--color-accent)">{{ $cbseLabel }}</a>
        @endif
        @if($showContact)
        <a href="{{ $contactUrl }}" class="block text-center px-3 py-2.5 rounded-xl text-sm font-bold text-white" style="background-color: var(--color-primary)">{{ $contactLabel }}</a>
        @endif
    </div>
    @if($show)
    <a href="{{ $url }}"
       class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold text-white"
       style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
        {{ $label }}
    </a>
    @endif
</div>
@endif
