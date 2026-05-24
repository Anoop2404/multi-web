{{-- Footer rendered from footer_config for this tenant --}}
@php $footerConfig = $footerConfig ?? []; @endphp
@include("partials.footers." . ($footerConfig['layout_variant'] ?? 'three-column'), ['content' => $footerConfig['content'] ?? []])
