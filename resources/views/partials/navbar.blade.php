{{-- Navbar rendered from nav_config for this tenant --}}
@php
    use App\Support\NavConfigDefaults;
    use App\Support\SectionVariantResolver;
    use App\Support\TenantBranding;

    $navConfig = NavConfigDefaults::resolve($tenant, $navConfig ?? []);
    if (isset($sections)) {
        $navConfig = NavConfigDefaults::pruneDeadAnchors($navConfig, $sections);
        if (($tenant->type ?? null) === 'school' && empty($tenant->getSetting('nav_config')['items'] ?? [])) {
            $navConfig = NavConfigDefaults::mergeSectionItems($navConfig, $sections);
        }
    }
    $navVariant = SectionVariantResolver::resolveNavVariant($navConfig);
    $logo = $logo ?? TenantBranding::logoUrl($tenant);
@endphp
@include("partials.navbars.{$navVariant}", [
    'items'     => $navConfig['items'] ?? [],
    'logo'      => $logo,
    'navConfig' => $navConfig,
    'homeUrl'   => $homeUrl ?? '/',
])
