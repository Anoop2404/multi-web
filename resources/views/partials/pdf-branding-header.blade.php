{{-- Shared Sahodaya branding header for PDF/print documents.
     Usage: @include('partials.pdf-branding-header', [
         'orgName' => $orgName ?? 'SAHODAYA SCHOOLS COMPLEX',
         'logoSrc' => $logoSrc ?? null,
         'docTitle' => $docTitle ?? null,
     ]) --}}
<div style="margin-bottom: 10px;">
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
        <tr>
            @if(!empty($logoSrc))
                <td style="width: 55px; vertical-align: middle; padding-right: 12px;">
                    <img src="{{ $logoSrc }}" alt="Logo" style="width: 48px; height: 48px; object-fit: contain;">
                </td>
            @endif
            <td style="vertical-align: middle;">
                <div style="font-size: 16px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.1;">
                    {{ $orgName ?? 'SAHODAYA SCHOOLS COMPLEX' }}
                </div>
                <div style="font-size: 10px; font-weight: 600; color: #475569; margin-top: 3px;">
                    CBSE Sahodaya Inter-School Competitions & Events
                </div>
            </td>
            @if(!empty($docTitle))
                <td style="text-align: right; vertical-align: middle;">
                    <div style="display: inline-block; background: #0f172a; color: #ffffff; padding: 4px 10px; border-radius: 4px; font-size: 9px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">
                        {{ $docTitle }}
                    </div>
                </td>
            @endif
        </tr>
    </table>
    <div style="border-bottom: 2px solid #0f172a; margin-bottom: 8px;"></div>
</div>
