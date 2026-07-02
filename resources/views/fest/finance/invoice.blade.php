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
</style>
</head><body>
<div class="header">
    <h2>{{ $sahodaya->name }}</h2>
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
    <thead><tr><th>Description</th><th style="width:120px;text-align:right">Amount (₹)</th></tr></thead>
    <tbody>
        <tr><td>School registration fee</td><td style="text-align:right">{{ number_format((float) $invoice->school_registration_fee, 2) }}</td></tr>
        <tr><td>Participation fee ({{ $invoice->participation_item_count }} item(s))</td><td style="text-align:right">{{ number_format((float) $invoice->participation_fee, 2) }}</td></tr>
    </tbody>
</table>
<p class="total">Total due: ₹{{ number_format((float) $invoice->total_amount, 2) }}</p>
<p style="margin-top:24px;font-size:10px;color:#666">This is a system-generated invoice. Payment proof should be uploaded via the school fest portal.</p>
</body></html>
