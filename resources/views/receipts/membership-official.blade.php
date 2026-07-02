<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receiptNo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Times New Roman", Times, serif;
            color: #0f172a;
            background: #fff;
            padding: 24px;
        }
        .receipt {
            max-width: 820px;
            margin: 0 auto;
            border: 2px solid {{ $template['accent_color'] ?? '#1e3a8a' }};
            padding: 28px 32px 32px;
            position: relative;
        }
        .receipt::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 14px;
            background: {{ $template['accent_color'] ?? '#1e3a8a' }};
        }
        .header {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            margin-bottom: 18px;
            padding-left: 8px;
        }
        .logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .logo-placeholder {
            width: 88px;
            height: 88px;
            border: 2px solid {{ $template['accent_color'] ?? '#1e3a8a' }};
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: {{ $template['accent_color'] ?? '#1e3a8a' }};
            flex-shrink: 0;
        }
        .org-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: {{ $template['accent_color'] ?? '#1e3a8a' }};
            text-transform: uppercase;
            line-height: 1.2;
        }
        .org-subtitle {
            font-size: 11px;
            margin-top: 6px;
            line-height: 1.45;
            color: #334155;
        }
        .org-office {
            font-size: 10px;
            margin-top: 4px;
            color: #475569;
            font-style: italic;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 22px;
            padding-left: 8px;
            gap: 12px;
        }
        .receipt-no {
            font-size: 18px;
            font-weight: 700;
            color: #dc2626;
        }
        .receipt-badge {
            background: {{ $template['accent_color'] ?? '#1e3a8a' }};
            color: #fff;
            font-weight: 700;
            letter-spacing: 2px;
            padding: 8px 28px;
            font-size: 18px;
        }
        .date-line {
            font-size: 14px;
            white-space: nowrap;
        }
        .body-line {
            font-size: 15px;
            line-height: 2.2;
            margin-bottom: 6px;
            padding-left: 8px;
        }
        .dotted {
            border-bottom: 1px dotted #64748b;
            display: inline-block;
            min-width: 120px;
            padding: 0 4px 2px;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 36px;
            padding-left: 8px;
            gap: 24px;
        }
        .amount-box {
            width: 120px;
            height: 90px;
            border: 2px solid {{ $template['accent_color'] ?? '#1e3a8a' }};
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .amount-box .symbol { font-size: 22px; margin-bottom: 4px; }
        .amount-box .value { font-size: 20px; }
        .signatures {
            display: flex;
            gap: 48px;
        }
        .signature-block {
            text-align: center;
            min-width: 160px;
        }
        .signature-line {
            border-top: 1px dotted #64748b;
            margin-bottom: 6px;
            height: 48px;
        }
        .signature-label {
            font-size: 12px;
            font-weight: 600;
        }
        @media print {
            body { padding: 0; }
            .receipt { border-width: 1px; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="Logo" class="logo">
            @else
                <div class="logo-placeholder">{{ strtoupper(substr($sahodayaName, 0, 1)) }}</div>
            @endif
            <div>
                <div class="org-title">{{ $template['header_title'] }}</div>
                @if(!empty($template['header_subtitle']))
                    <div class="org-subtitle">{{ $template['header_subtitle'] }}</div>
                @endif
                @if(!empty($template['registered_office']))
                    <div class="org-office">{{ $template['registered_office'] }}</div>
                @endif
            </div>
        </div>

        <div class="meta-row">
            <div class="receipt-no">No. <span>{{ $receiptNo }}</span></div>
            <div class="receipt-badge">RECEIPT</div>
            <div class="date-line">Date: <span class="dotted">{{ $receiptDate }}</span></div>
        </div>

        <p class="body-line">
            Received with thanks from <span class="dotted" style="min-width:55%">{{ $schoolName }}</span>
        </p>
        <p class="body-line">
            Rupees <span class="dotted" style="min-width:70%">{{ $amountWords }}</span>
        </p>
        <p class="body-line">
            being <span class="dotted" style="min-width:45%">{{ $purpose }}</span>
            by <span class="dotted">{{ $paymentMethod }}</span>
            on <span class="dotted">{{ $receiptDate }}</span>
        </p>
        <p class="body-line">
            Reference details <span class="dotted" style="min-width:60%">{{ $transactionRef ?: '—' }}</span>
        </p>

        <div class="footer">
            <div class="amount-box">
                <div class="symbol">₹</div>
                <div class="value">{{ number_format($amount, 2) }}</div>
            </div>
            <div class="signatures">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ $template['receiver_label'] ?? 'Receiver Signature' }}</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ $template['counter_label'] ?? 'Counter Signature' }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
