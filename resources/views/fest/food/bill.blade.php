<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Food Bill — {{ $bill->school?->name ?? $bill->school_id }}</title>
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
<div class="header">
    @include('partials.pdf-branding-header', ['orgName' => $sahodaya->name ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
    <p>Food Bill</p>
    <p><strong>{{ $event->title }}</strong></p>
</div>
<div class="meta">
    <p><strong>School:</strong> {{ $bill->school?->name ?? $bill->school_id }}</p>
    <p><strong>Status:</strong> {{ ucfirst($bill->status) }}</p>
    <p><strong>Payable to:</strong> {{ $bill->payee_type === 'host_school' ? ($bill->hostSchool?->name ?? 'Host school') : ($sahodaya->name ?? 'Sahodaya') }}</p>
    <p><strong>Date:</strong> {{ now()->format('d M Y') }}</p>
</div>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Meal</th>
            <th>Item</th>
            <th style="width:60px;text-align:right">Qty</th>
            <th style="width:90px;text-align:right">Unit (₹)</th>
            <th style="width:100px;text-align:right">Total (₹)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bill->orderItems as $oi)
            <tr>
                <td>{{ optional($oi->menu_date)->format('d M Y') }}</td>
                <td style="text-transform:capitalize">{{ $oi->meal_type }}</td>
                <td>{{ $oi->item_name }}</td>
                <td style="text-align:right">{{ $oi->quantity }}</td>
                <td style="text-align:right">{{ number_format((float) $oi->unit_price, 2) }}</td>
                <td style="text-align:right">{{ number_format((float) $oi->line_total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#666">No items ordered.</td></tr>
        @endforelse
    </tbody>
</table>
<p class="total">Total: ₹{{ number_format((float) $bill->amount_total, 2) }}</p>

<table>
    <thead>
        <tr>
            <th>Receipt #</th>
            <th style="width:100px;text-align:right">Amount (₹)</th>
            <th style="width:100px">Mode</th>
            <th style="width:110px">Received</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bill->payments as $p)
            <tr>
                <td>{{ $p->receipt_number }}</td>
                <td style="text-align:right">{{ number_format((float) $p->amount, 2) }}</td>
                <td style="text-transform:capitalize">{{ str_replace('_', ' ', $p->payment_mode) }}</td>
                <td>{{ optional($p->received_at)->format('d M Y') }}</td>
                <td>{{ $p->notes }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#666">No payments recorded.</td></tr>
        @endforelse
    </tbody>
</table>
<p class="total">Paid: ₹{{ number_format((float) $bill->amount_paid, 2) }}</p>
<p class="total" style="color:{{ $bill->balanceDue() > 0 ? '#b45309' : '#0a7a4d' }}">Balance due: ₹{{ number_format($bill->balanceDue(), 2) }}</p>

<p class="muted" style="margin-top:24px">This is a system-generated food bill receipt.</p>
</body></html>
