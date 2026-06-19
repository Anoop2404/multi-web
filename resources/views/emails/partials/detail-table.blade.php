<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
    @foreach($rows as $label => $value)
        @if(filled($value))
        <tr>
            <td style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;background:#f8fafc;border-bottom:1px solid #e2e8f0;width:38%;vertical-align:top;">{{ $label }}</td>
            <td style="padding:10px 14px;font-size:14px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $value }}</td>
        </tr>
        @endif
    @endforeach
</table>
