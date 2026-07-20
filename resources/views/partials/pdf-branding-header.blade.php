{{-- Shared Sahodaya branding header for PDF/print documents.
     Usage: @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
     Renders a logo (if available) + org name block. Never breaks layout if logoSrc is missing. --}}
<table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
    <tr>
        @if(!empty($logoSrc))
            <td style="width:58px;vertical-align:middle;padding:0 10px 0 0;">
                <img src="{{ $logoSrc }}" alt="" style="width:48px;height:48px;object-fit:contain;">
            </td>
        @endif
        <td style="vertical-align:middle;">
            <div style="font-size:14px;font-weight:700;color:#0f2744;">{{ $orgName ?? 'Sahodaya' }}</div>
        </td>
    </tr>
</table>
