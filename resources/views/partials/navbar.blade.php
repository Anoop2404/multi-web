{{-- Navbar rendered from nav_config for this tenant --}}
@php $navConfig = $navConfig ?? []; @endphp
@include("partials.navbars." . ($navConfig['layout_variant'] ?? 'logo-left'), ['items' => $navConfig['items'] ?? []])
