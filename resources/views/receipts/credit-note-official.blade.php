<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Note {{ $noteNo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; color: #0f172a; background: #fff; padding: 24px; }
        .receipt {
            max-width: 760px;
            margin: 0 auto;
            border: 2px solid #b45309;
            padding: 24px 28px 28px;
            position: relative;
        }
        .receipt::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 12px;
            background: #b45309;
        }
        .header { padding-left: 8px; margin-bottom: 16px; }
        .org-info h1 { font-size: 18px; font-weight: 700; color: #b45309; text-transform: uppercase; }
        .org-info p { font-size: 11px; color: #475569; margin-top: 2px; }
        .receipt-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #b45309;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 0;
            margin: 0 0 16px 8px;
        }
        .receipt-no {
            position: absolute;
            top: 24px;
            right: 28px;
            text-align: right;
        }
        .receipt-no .num { font-size: 22px; font-weight: 700; color: #b45309; }
        .receipt-no .label { font-size: 10px; color: #64748b; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-left: 8px; }
        td { padding: 6px 0; border-bottom: 1px dotted #e2e8f0; }
        td:first-child { width: 38%; color: #64748b; font-size: 12px; }
        .amount-row td { padding-top: 12px; font-size: 16px; font-weight: 700; border-bottom: none; }
        .amount-row td:last-child { color: #b45309; }
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: #fef3c7;
            color: #92400e;
        }
        .terminal-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: #dcfce7;
            color: #166534;
        }
        .footer { margin-top: 20px; margin-left: 8px; display: flex; justify-content: space-between; align-items: flex-end; }
        .sign-line { border-top: 1px solid #334155; width: 150px; margin: 0 0 4px; }
        .sign-label { font-size: 11px; color: #475569; }
        .watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 60px;
            font-weight: 900;
            color: rgba(180,83,9,0.08);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 4px;
        }
        @media print { body { padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="watermark">Credit</div>

        <div class="receipt-no">
            <p class="label">Credit Note No.</p>
            <p class="num">{{ $noteNo }}</p>
        </div>

        <div class="header">
            <div class="org-info">
                <h1>{{ $sahodaya->name }}</h1>
                <p>Sahodaya Schools Complex · Fee Credit Note</p>
                <p>Date: {{ $issuedAt->format('d M Y') }}</p>
            </div>
        </div>

        <div class="receipt-title">Fee Credit Note</div>

        <table>
            <tr>
                <td>Issued to</td>
                <td><strong>{{ $school->name }}</strong></td>
            </tr>
            <tr>
                <td>Programme</td>
                <td>{{ $contextLabel }}</td>
            </tr>
            <tr>
                <td>Reason</td>
                <td>{{ $reason ?: 'Not specified' }}</td>
            </tr>
            <tr>
                <td>Issued by</td>
                <td>{{ $issuedBy }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    @if($appliedAt)
                        <span class="terminal-badge">Applied {{ $appliedAt->format('d M Y') }}</span>
                    @else
                        <span class="status-badge">Outstanding</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Amount in words</td>
                <td>{{ $amountWords }}</td>
            </tr>
            <tr class="amount-row">
                <td>Credit Amount</td>
                <td>₹ {{ number_format($amount, 2) }}</td>
            </tr>
        </table>

        <p style="margin:16px 0 0 8px;font-size:11px;color:#64748b;line-height:1.5;">
            This credit note records an amount owed back to the school above, arising from a rejected or
            cancelled paid registration. It is not a cash refund — the platform does not move money directly.
            The amount will be automatically offset against a future fee for the same programme when a
            Sahodaya admin applies it, or can be recorded as an out-of-platform bank payout by the Sahodaya
            office against this credit.
        </p>

        <div class="footer">
            <div>
                <div class="sign-line"></div>
                <p class="sign-label">Authorised Signatory</p>
                <p class="sign-label">{{ $sahodaya->name }}</p>
            </div>
            <p style="font-size:10px;color:#94a3b8;">This is a computer-generated credit note.</p>
        </div>
    </div>
    <p class="no-print" style="text-align:center;margin-top:16px;font-family:sans-serif;font-size:13px;">
        <button onclick="window.print()" style="padding:6px 18px;background:#b45309;color:#fff;border:none;border-radius:6px;cursor:pointer;">Print Credit Note</button>
    </p>
</body>
</html>
