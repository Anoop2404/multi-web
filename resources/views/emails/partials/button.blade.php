@php
    $bg = match($variant ?? 'primary') {
        'success' => '#059669',
        'danger'  => '#dc2626',
        'amber'   => '#d97706',
        default   => '#0f3d7a',
    };
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:{{ $margin ?? '20px 0' }};">
    <tr>
        <td align="center" style="border-radius:10px;background:{{ $bg }};">
            <a href="{{ $url }}" target="_blank"
               style="display:inline-block;padding:14px 28px;font-family:'Segoe UI',Inter,Arial,sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;line-height:1;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>
