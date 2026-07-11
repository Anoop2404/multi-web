<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNo }}</title>
    <style>
        @page { margin: 36px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f2744; }
        .header { border-bottom: 2px solid #0f2744; padding-bottom: 10px; margin-bottom: 16px; }
        .header table { width: 100%; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .org { font-size: 15px; font-weight: 700; }
        .tag { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .title { font-size: 14px; font-weight: 700; margin: 12px 0 4px; }
        .meta p { margin: 3px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.lines th { background: #0f2744; color: #fff; text-align: left; padding: 8px; font-size: 9px; text-transform: uppercase; }
        table.lines td { border: 1px solid #cbd5e1; padding: 8px; }
        .total { text-align: right; font-size: 13px; font-weight: 700; margin-top: 12px; }
        .muted { color: #64748b; font-size: 9px; margin-top: 24px; }
    </style>
</head>
<body>
<div class="header">
    <table>
        <tr>
            @if(!empty($logoSrc))
                <td style="width:58px;vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
            @endif
            <td style="vertical-align:middle;">
                <div class="org">{{ $orgName }}</div>
                <div class="tag">Talent Search · Fee invoice</div>
            </td>
        </tr>
    </table>
</div>

<div class="title">{{ $exam->title }}</div>
@if($exam->code)
    <p class="meta">Exam code: <strong>{{ $exam->code }}</strong></p>
@endif

<div class="meta">
    <p><strong>Invoice #:</strong> {{ $invoiceNo }}</p>
    <p><strong>Participant:</strong> {{ $participant }}</p>
    <p><strong>Reg. / Ticket:</strong> {{ $regNo ?: '—' }}</p>
    <p><strong>School:</strong> {{ $school?->name ?? '—' }}</p>
    <p><strong>Date:</strong> {{ now()->format('d M Y') }}</p>
    <p><strong>Payment status:</strong> {{ $feeStatus ?: 'pending' }}</p>
</div>

<table class="lines">
    <thead>
        <tr>
            <th>Description</th>
            <th style="width:120px;text-align:right">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Student exam fee</td>
            <td style="text-align:right">{{ number_format((float) $studentFee, 2) }}</td>
        </tr>
        @if((float) $discount > 0)
            <tr>
                <td>School discount</td>
                <td style="text-align:right">−{{ number_format((float) $discount, 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<p class="total">Amount due: ₹{{ number_format((float) $payable, 2) }}</p>
<p class="muted">System-generated invoice for this registration. Upload payment proof via the school Talent Search portal. Generated {{ $generatedAt }}.</p>
</body>
</html>
