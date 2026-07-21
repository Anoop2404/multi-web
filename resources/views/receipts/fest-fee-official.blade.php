<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ strtoupper($event->title) }} Fee Receipt {{ $receipt->receipt_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; color: #0f172a; background: #fff; padding: 24px; }
        .receipt {
            max-width: 760px;
            margin: 0 auto;
            border: 2px solid #1e3a8a;
            padding: 24px 28px 28px;
            position: relative;
        }
        .receipt::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 12px;
            background: #1e3a8a;
        }
        .header { padding-left: 8px; display: flex; gap: 16px; align-items: center; margin-bottom: 16px; }
        .header img { height: 56px; width: auto; object-fit: contain; }
        .org-info h1 { font-size: 19px; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; }
        .org-info p { font-size: 11px; color: #475569; margin-top: 2px; }
        .receipt-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #1e3a8a;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 7px 0;
            margin: 0 0 16px 8px;
            background: #f8fafc;
        }
        .receipt-no {
            position: absolute;
            top: 24px;
            right: 28px;
            text-align: right;
        }
        .receipt-no .num { font-size: 22px; font-weight: 700; color: #1e3a8a; font-family: monospace; }
        .receipt-no .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-left: 8px; }
        td { padding: 6px 0; border-bottom: 1px dotted #e2e8f0; }
        td:first-child { width: 38%; color: #64748b; font-size: 12px; }
        .amount-row td { padding-top: 12px; font-size: 16px; font-weight: 700; border-bottom: none; }
        .amount-row td:last-child { color: #16a34a; font-size: 18px; }
        .items-list { margin: 6px 0 0 8px; font-size: 12px; }
        .items-list li { margin-bottom: 3px; }
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: #dcfce7;
            color: #166534;
        }
        .footer { margin-top: 24px; margin-left: 8px; display: flex; justify-content: space-between; align-items: flex-end; }
        .sign-line { border-top: 1px solid #334155; width: 160px; margin: 0 0 4px; }
        .sign-label { font-size: 11px; color: #475569; }
        .watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 64px;
            font-weight: 900;
            color: rgba(22,163,74,0.07);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 4px;
        }
        @media print { body { padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    @php
        $logoUrl = \App\Support\TenantBranding::logoUrl($sahodaya);
        $amountWords = \App\Support\IndianAmountInWords::rupees((float) $receipt->amount);
    @endphp

    <div class="receipt">
        <div class="watermark">Paid</div>

        <div class="receipt-no">
            <p class="label">Receipt No.</p>
            <p class="num">{{ $receipt->receipt_number }}</p>
        </div>

        <div class="header">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $sahodaya->name }}">
            @endif
            <div class="org-info">
                <h1>{{ $sahodaya->name }}</h1>
                <p>Sahodaya Schools Complex · Official Fee Receipt</p>
                <p>Date: {{ $receipt->payment_date?->format('d M Y') ?? now()->format('d M Y') }}</p>
            </div>
        </div>

        <div class="receipt-title">{{ strtoupper($event->title) }} — OFFICIAL FEE RECEIPT</div>

        <table>
            <tr>
                <td>Received from</td>
                <td><strong>{{ $school->name }}</strong></td>
            </tr>
            <tr>
                <td>Event Title</td>
                <td><strong>{{ $event->title }}</strong></td>
            </tr>
            @if($receipt->transaction_ref)
            <tr>
                <td>Transaction Ref.</td>
                <td>{{ $receipt->transaction_ref }}</td>
            </tr>
            @endif
            @if($receipt->bank_name)
            <tr>
                <td>Bank / Payment Method</td>
                <td>{{ $receipt->bank_name }}</td>
            </tr>
            @endif
            <tr>
                <td>Payment Date</td>
                <td>{{ $receipt->payment_date?->format('d M Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    <span class="status-badge">Approved</span>
                </td>
            </tr>
            @foreach(($breakdown['items'] ?? []) as $line)
            <tr>
                <td>{{ $line['label'] }}</td>
                <td>₹ {{ number_format($line['amount'], 2) }}</td>
            </tr>
            @endforeach
            <tr>
                <td>Registered items ({{ $breakdown['item_count'] ?? $registrations->count() }})</td>
                <td>
                    <ul class="items-list">
                        @foreach($registrations as $reg)
                        <li>{{ $reg->item->title ?? 'Item' }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
            <tr>
                <td>Amount in Words</td>
                <td><strong>{{ $amountWords }}</strong></td>
            </tr>
            <tr class="amount-row">
                <td>Total Amount Paid</td>
                <td>₹ {{ number_format((float) $receipt->amount, 2) }}</td>
            </tr>
        </table>

        <div class="footer">
            <div>
                <div class="sign-line"></div>
                <p class="sign-label">Authorised Signatory</p>
                <p class="sign-label">{{ $sahodaya->name }}</p>
            </div>
            <p style="font-size:10px;color:#94a3b8;">This is an official computer-generated receipt.</p>
        </div>
    </div>
    <p class="no-print" style="text-align:center;margin-top:16px;font-family:sans-serif;font-size:13px;">
        <button onclick="window.print()" style="padding:8px 22px;background:#1e3a8a;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">Print Receipt</button>
    </p>
</body>
</html>
