@extends('emails.layouts.sahodaya')

@section('content')
    <div style="font-size:15px;line-height:1.6;color:#334155;">
        <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#0f172a;">
            Dear Principal / Sahodaya In-Charge, <span style="color:#1e40af;">{{ $schoolName }}</span>,
        </p>

        <div style="margin:0 0 20px;white-space:pre-line;color:#334155;line-height:1.65;">{{ $messageBody }}</div>

        @if(!empty($slots) && count($slots) > 0)
            <div style="margin:24px 0;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <div style="background-color:#f8fafc;padding:12px 16px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;">
                    Qualified Student Slots Requiring Your Response ({{ count($slots) }})
                </div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;border-collapse:collapse;">
                    <thead>
                        <tr style="background-color:#f1f5f9;color:#475569;border-bottom:1px solid #e2e8f0;">
                            <th align="left" style="padding:10px 14px;font-weight:600;">Student</th>
                            <th align="left" style="padding:10px 14px;font-weight:600;">Item / Event</th>
                            <th align="center" style="padding:10px 14px;font-weight:600;">Rank</th>
                            <th align="center" style="padding:10px 14px;font-weight:600;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slots as $s)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px 14px;font-weight:600;color:#0f172a;">
                                    {{ $s['student_name'] }}
                                    @if(!empty($s['class_name']))
                                        <span style="font-size:11px;font-weight:normal;color:#64748b;display:block;">Class: {{ $s['class_name'] }}</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;color:#334155;">
                                    <strong>{{ $s['item_code'] ?? '' }}</strong> {{ $s['item_title'] ?? '' }}
                                    @if(!empty($s['category_label']))
                                        <span style="font-size:11px;color:#64748b;display:block;">{{ $s['category_label'] }}</span>
                                    @endif
                                </td>
                                <td align="center" style="padding:10px 14px;font-weight:700;color:#1e40af;">
                                    {{ $s['source_position'] ? 'Rank ' . $s['source_position'] : 'Direct' }}
                                </td>
                                <td align="center" style="padding:10px 14px;">
                                    @if(($s['school_response'] ?? '') === 'accepted')
                                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;background-color:#dcfce7;color:#166534;">Confirmed</span>
                                    @elseif(($s['school_response'] ?? '') === 'opted_out')
                                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;background-color:#f1f5f9;color:#64748b;">Opted Out</span>
                                    @else
                                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;background-color:#fef3c7;color:#92400e;">Response Required</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div style="margin:28px 0;text-align:center;">
            <a href="{{ $actionUrl }}"
               target="_blank"
               style="display:inline-block;background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 32px;border-radius:8px;box-shadow:0 4px 6px -1px rgba(37,99,235,0.25);">
                Review &amp; Confirm State Slots on Portal →
            </a>
            <p style="margin:10px 0 0;font-size:12px;color:#64748b;">
                Or copy this link to your browser: <br>
                <a href="{{ $actionUrl }}" style="color:#2563eb;word-break:break-all;">{{ $actionUrl }}</a>
            </p>
        </div>

        <div style="margin-top:24px;padding:14px 18px;background-color:#f8fafc;border-left:4px solid #3b82f6;border-radius:0 8px 8px 0;font-size:12px;color:#475569;line-height:1.5;">
            <strong>Important:</strong> If any qualified student is unable to represent the Sahodaya at the State event, kindly select <em>"Opt Out"</em> on the portal promptly so the slot rolls down in merit order to waiting list candidates.
        </div>
    </div>
@endsection
