<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fest Invoice {{ $invoice->invoice_number }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}
.header{text-align:center;margin-bottom:20px}
.meta{margin-bottom:16px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{border:1px solid #ccc;padding:8px;text-align:left}
th{background:#1d3557;color:#fff}
.total{font-size:14px;font-weight:bold;text-align:right;margin-top:12px}
.muted{color:#666;font-size:10px}
</style>
</head><body>
@php
    $participationLines = $participationLines ?? [];
    $showHeadColumn = collect($participationLines)->contains(fn ($line) => ! empty($line['head_name'] ?? null));
    $headColumnLabel = ($event->event_type ?? null) === 'sports' ? 'Event Head' : 'Item head';
    $sl = 0;
@endphp
<div class="header">
    @include('partials.pdf-branding-header', ['orgName' => $sahodaya->name ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
    <p>Fest Participation Invoice</p>
    <p><strong>{{ $event->title }}</strong></p>
</div>
<div class="meta">
    <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
    <p><strong>School:</strong> {{ $invoice->school?->name ?? $invoice->school_id }}</p>
    <p><strong>Date:</strong> {{ $invoice->issued_at?->format('d M Y') ?? now()->format('d M Y') }}</p>
    <p><strong>Participation items:</strong> {{ $invoice->participation_item_count }}</p>
</div>
<table>
    <thead>
        <tr>
            @if($showHeadColumn)
                <th>{{ $headColumnLabel }}</th>
                <th>Item</th>
            @else
                <th>Description</th>
            @endif
            <th style="width:120px;text-align:right">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        @if((float) $invoice->school_registration_fee > 0)
            <tr>
                @if($showHeadColumn)
                    <td colspan="2">School registration fee</td>
                @else
                    <td>School registration fee</td>
                @endif
                <td style="text-align:right">{{ number_format((float) $invoice->school_registration_fee, 2) }}</td>
            </tr>
        @endif
        @forelse($participationLines as $line)
            <tr>
                @if($showHeadColumn)
                    <td>{{ $line['head_name'] ?? '—' }}</td>
                    <td>{{ $line['item_title'] ?? $line['label'] }}</td>
                @else
                    <td>{{ $line['label'] ?? $line['item_title'] }}</td>
                @endif
                <td style="text-align:right">{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
            </tr>
        @empty
            @if((float) $invoice->participation_fee > 0)
                <tr>
                    @if($showHeadColumn)
                        <td colspan="2">Participation fee ({{ $invoice->participation_item_count }} item(s))</td>
                    @else
                        <td>Participation fee ({{ $invoice->participation_item_count }} item(s))</td>
                    @endif
                    <td style="text-align:right">{{ number_format((float) $invoice->participation_fee, 2) }}</td>
                </tr>
            @endif
        @endforelse
    </tbody>
</table>
<p class="total">Total due: ₹{{ number_format((float) $invoice->total_amount, 2) }}</p>
<p class="muted" style="margin-top:24px">This is a system-generated invoice. Payment proof should be uploaded via the school fest portal.</p>
</body></html>
