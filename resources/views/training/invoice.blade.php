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
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; }
        .badge-issued { background: #e0e7ff; color: #3730a3; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-draft { background: #f1f5f9; color: #475569; }
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
                <div class="tag">Teacher training · Fee invoice</div>
            </td>
        </tr>
    </table>
</div>

<div class="title">{{ $program?->title ?? 'Training Programme' }}</div>
@if($program?->code)
    <p class="meta">Programme code: <strong>{{ $program->code }}</strong></p>
@endif

<div class="meta">
    <p><strong>Invoice #:</strong> {{ $invoiceNo }}</p>
    <p>
        <strong>Status:</strong>
        <span class="badge badge-{{ $status }}">{{ $status }}</span>
    </p>
    @if(!empty($participant))
        <p><strong>Participant:</strong> {{ $participant }}</p>
    @endif
    @if(!empty($teacherCount))
        <p><strong>Teachers covered:</strong> {{ $teacherCount }}</p>
    @endif
    <p><strong>School:</strong> {{ $school?->name ?? '—' }}</p>
    <p><strong>Issued:</strong> {{ $invoice->issued_at?->format('d M Y') ?? now()->format('d M Y') }}</p>
</div>

<table class="lines">
    <thead>
        <tr>
            <th>Description</th>
            <th style="width:120px;text-align:right">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lineItems as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td style="text-align:right">{{ number_format((float) $item['amount'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="total">Amount due: ₹{{ number_format((float) $amount, 2) }}</p>
<p class="muted">System-generated training invoice. Upload payment proof via the school training portal. Generated {{ $generatedAt }}.</p>
</body>
</html>
