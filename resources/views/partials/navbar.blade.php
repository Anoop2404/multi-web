{{-- Navbar rendered from nav_config for this tenant --}}
@php
    use App\Support\SectionVariantResolver;

    $navConfig = $navConfig ?? [];
    $navVariant = SectionVariantResolver::resolveNavVariant($navConfig);
    $logo = $logo ?? ($tenant->getSetting('logo') ?? null);
@endphp
@include("partials.navbars.{$navVariant}", ['items' => $navConfig['items'] ?? [], 'logo' => $logo])
