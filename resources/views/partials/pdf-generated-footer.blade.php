{{-- Shared per-page "Generated on" timestamp for PDF reports. Uses dompdf's
     position:fixed support, which repeats an element on every page of the
     rendered PDF (not just the page it's written on) -- so multi-page reports
     keep showing the timestamp after page 1 instead of only on the first page.
     Include this once, right after <body>, in every report Blade file.
     Usage: @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null]) --}}
<div style="position: fixed; bottom: 0px; left: 0px; right: 0px; font-size: 7.5px; color: #94a3b8; text-align: right; border-top: 0.5px solid #e2e8f0; padding-top: 2px;">
    Generated on {{ $generatedAt ?? now()->format('d M Y, h:i A') }}
</div>
