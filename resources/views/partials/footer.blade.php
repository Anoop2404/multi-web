{{-- Footer rendered from footer_config for this tenant --}}
@php
    use App\Support\SectionVariantResolver;

    $footerConfig = $footerConfig ?? [];
    $footerVariant = SectionVariantResolver::resolveFooterVariant($footerConfig);
    $content = $footerConfig['content'] ?? array_diff_key($footerConfig, array_flip(['style', 'layout_variant']));
@endphp
@include("partials.footers.{$footerVariant}", ['content' => $content])
