@php
    $styles = match($variant ?? 'info') {
        'success' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#065f46'],
        'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e'],
        'danger'  => ['bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b'],
        default   => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e3a8a'],
    };
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:{{ $margin ?? '0 0 18px' }};">
    <tr>
        <td style="background:{{ $styles['bg'] }};border:1px solid {{ $styles['border'] }};border-radius:10px;padding:14px 16px;">
            @if(!empty($title))
            <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:{{ $styles['text'] }};">{{ $title }}</p>
            @endif
            <p style="margin:0;font-size:14px;line-height:1.55;color:{{ $styles['text'] }};">{!! $message ?? '' !!}</p>
        </td>
    </tr>
</table>
