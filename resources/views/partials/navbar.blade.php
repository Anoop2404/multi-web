{{-- Navbar rendered from nav_config for this tenant --}}
@php
    use App\Support\NavConfigDefaults;
    use App\Support\SectionVariantResolver;
    use App\Support\TenantBranding;

    $navConfig = NavConfigDefaults::resolve($tenant, $navConfig ?? []);
    $navVariant = SectionVariantResolver::resolveNavVariant($navConfig);
    $logo = $logo ?? TenantBranding::logoUrl($tenant);
@endphp
@include("partials.navbars.{$navVariant}", [
    'items'     => $navConfig['items'] ?? [],
    'logo'      => $logo,
    'navConfig' => $navConfig,
])
