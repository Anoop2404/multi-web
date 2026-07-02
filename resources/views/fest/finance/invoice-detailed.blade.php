<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fest Payment Demand — {{ $invoice->invoice_number }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111;line-height:1.45}
.header{border-bottom:3px double #1d3557;padding-bottom:12px;margin-bottom:16px}
.meta td{padding:4px 8px;vertical-align:top}
table.data{width:100%;border-collapse:collapse;margin-top:12px}
table.data th,table.data td{border:1px solid #ccc;padding:8px}
table.data th{background:#1d3557;color:#fff;text-align:left}
.total-box{margin-top:16px;border:2px solid #1d3557;padding:12px;text-align:right}
.signatures{margin-top:40px;display:table;width:100%}
.sig{display:table-cell;width:33%;text-align:center;padding-top:40px;border-top:1px solid #999}
</style>
</head><body>
<div class="header">
    <h2 style="margin:0">{{ $sahodaya->name }}</h2>
    <p style="margin:4px 0 0">Festival Participation — Payment Demand Notice</p>
    <p style="margin:4px 0 0"><strong>{{ $event->title }}</strong></p>
</div>
<table class="meta" style="width:100%">
    <tr>
        <td width="50%"><strong>Invoice No:</strong> {{ $invoice->invoice_number }}</td>
        <td><strong>Date:</strong> {{ $invoice->issued_at?->format('d-m-Y') ?? now()->format('d-m-Y') }}</td>
    </tr>
    <tr>
        <td><strong>School:</strong> {{ $invoice->school?->name ?? $invoice->school_id }}</td>
        <td><strong>Status:</strong> {{ strtoupper($invoice->status) }}</td>
    </tr>
    <tr>
        <td colspan="2"><strong>Participation items:</strong> {{ $invoice->participation_item_count }}</td>
    </tr>
</table>
<table class="data">
    <thead><tr><th>Sl</th><th>Particulars</th><th style="text-align:right;width:120px">Amount (₹)</th></tr></thead>
    <tbody>
        <tr><td>1</td><td>School registration fee</td><td style="text-align:right">{{ number_format((float) $invoice->school_registration_fee, 2) }}</td></tr>
        <tr><td>2</td><td>Participation fee ({{ $invoice->participation_item_count }} item(s))</td><td style="text-align:right">{{ number_format((float) $invoice->participation_fee, 2) }}</td></tr>
    </tbody>
</table>
<div class="total-box">
    <strong>Total Amount Due: ₹{{ number_format((float) $invoice->total_amount, 2) }}</strong>
</div>
<p style="margin-top:20px;font-size:10px;color:#444">
    Please remit payment to the Sahodaya account and upload proof via your school fest portal.
    This is a computer-generated demand notice.
</p>
<div class="signatures">
    <div class="sig">School Principal</div>
    <div class="sig">Sahodaya Secretary</div>
    <div class="sig">Finance Officer</div>
</div>
</body></html>
