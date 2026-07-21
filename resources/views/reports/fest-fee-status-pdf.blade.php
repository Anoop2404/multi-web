<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ strtoupper($event->title) }} — Fee Status Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-b: 2px solid #0f3d7a;
            padding-bottom: 10px;
        }
        .header-logo {
            width: 70px;
            vertical-align: top;
        }
        .header-logo img {
            max-width: 65px;
            max-height: 65px;
            object-fit: contain;
        }
        .header-title {
            vertical-align: top;
            padding-left: 10px;
        }
        .header-title h1 {
            font-size: 18px;
            margin: 0 0 3px 0;
            color: #0f3d7a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-title h2 {
            font-size: 13px;
            margin: 0 0 4px 0;
            color: #334155;
            font-weight: 600;
        }
        .header-title p {
            font-size: 10px;
            margin: 0;
            color: #64748b;
        }
        .header-meta {
            text-align: right;
            vertical-align: top;
            font-size: 10px;
            color: #475569;
        }
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .summary-box td {
            padding: 8px 12px;
            border-right: 1px solid #e2e8f0;
            text-align: center;
        }
        .summary-box td:last-child {
            border-right: none;
        }
        .summary-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .summary-value.text-green { color: #166534; }
        .summary-value.text-amber { color: #92400e; }
        .summary-value.text-red { color: #991b1b; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .data-table th {
            background: #0f3d7a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.4px;
            padding: 7px 8px;
            border: 1px solid #0f3d7a;
            text-align: left;
        }
        .data-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-approved { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-proof_uploaded { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-partial { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-pending { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .footer-notes {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .footer-notes td {
            vertical-align: top;
            font-size: 9.5px;
            color: #64748b;
        }
        .sign-box {
            text-align: right;
            padding-right: 10px;
        }
        .sign-title {
            font-weight: bold;
            color: #1e293b;
            margin-top: 30px;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            @if($logoUrl)
                <td class="header-logo">
                    <img src="{{ $logoUrl }}" alt="{{ $sahodaya->name }}">
                </td>
            @endif
            <td class="header-title">
                <h1>{{ $sahodaya->name }}</h1>
                <h2>{{ strtoupper($event->title) }} — FEE STATUS REPORT</h2>
                <p>Academic Year: {{ $event->academic_year_id ?? '2026-27' }} · Event Date: {{ $event->event_start ? \Carbon\Carbon::parse($event->event_start)->format('d M Y') : '—' }}</p>
            </td>
            <td class="header-meta">
                <p><strong>Report Date:</strong> {{ $generatedAt }}</p>
                <p><strong>Total Schools:</strong> {{ $summary['total_schools'] }}</p>
            </td>
        </tr>
    </table>

    <table class="summary-box">
        <tr>
            <td>
                <div class="summary-label">Participating Schools</div>
                <div class="summary-value">{{ $summary['total_schools'] }}</div>
            </td>
            <td>
                <div class="summary-label">Total Billed Due</div>
                <div class="summary-value">₹{{ number_format($summary['total_due'], 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Total Collected (Paid)</div>
                <div class="summary-value text-green">₹{{ number_format($summary['total_paid'], 2) }}</div>
            </td>
            <td>
                <div class="summary-label">Pending Balance</div>
                <div class="summary-value {{ $summary['total_balance'] > 0 ? 'text-red' : 'text-green' }}">
                    ₹{{ number_format($summary['total_balance'], 2) }}
                </div>
            </td>
            <td>
                <div class="summary-label">Paid / Approved</div>
                <div class="summary-value text-green">{{ $summary['approved'] }} schools</div>
            </td>
            <td>
                <div class="summary-label">Proof Pending</div>
                <div class="summary-value text-amber">{{ $summary['proof_uploaded'] }} schools</div>
            </td>
            <td>
                <div class="summary-label">Unpaid / Pending</div>
                <div class="summary-value text-red">{{ $summary['pending'] }} schools</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th>School Name</th>
                <th style="width: 75px;" class="text-center">Items</th>
                <th style="width: 85px;" class="text-right">Total Due (₹)</th>
                <th style="width: 85px;" class="text-right">Amount Paid (₹)</th>
                <th style="width: 85px;" class="text-right">Balance (₹)</th>
                <th style="width: 95px;" class="text-center">Status</th>
                <th style="width: 100px;">Receipt # / Ref</th>
                <th style="width: 85px;" class="text-center">Payment Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $row['school_name'] }}</td>
                    <td class="text-center">{{ $row['item_count'] }}</td>
                    <td class="text-right font-bold">₹{{ number_format($row['total_due'], 2) }}</td>
                    <td class="text-right" style="color: #15803d; font-weight: bold;">₹{{ number_format($row['amount_paid'], 2) }}</td>
                    <td class="text-right" style="{{ $row['balance_due'] > 0 ? 'color: #b91c1c; font-weight: bold;' : 'color: #64748b;' }}">
                        ₹{{ number_format($row['balance_due'], 2) }}
                    </td>
                    <td class="text-center">
                        @php
                            $st = $row['status'];
                            $labelMap = [
                                'approved' => 'Approved',
                                'proof_uploaded' => 'Proof Pending',
                                'partial' => 'Partial Paid',
                                'pending' => 'Pending',
                                'rejected' => 'Rejected',
                            ];
                            $label = $labelMap[$st] ?? ucfirst($st);
                        @endphp
                        <span class="badge badge-{{ $st }}">{{ $label }}</span>
                    </td>
                    <td>{{ $row['receipt_no'] ?: ($row['txn_ref'] ?: '—') }}</td>
                    <td class="text-center">{{ $row['payment_date'] ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #64748b;">
                        No school fee records found for this event.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rows) > 0)
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td colspan="3" class="text-right">TOTALS:</td>
                    <td class="text-right">₹{{ number_format($summary['total_due'], 2) }}</td>
                    <td class="text-right" style="color: #15803d;">₹{{ number_format($summary['total_paid'], 2) }}</td>
                    <td class="text-right" style="{{ $summary['total_balance'] > 0 ? 'color: #b91c1c;' : '' }}">
                        ₹{{ number_format($summary['total_balance'], 2) }}
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="footer-notes">
        <tr>
            <td>
                <p>This report is generated automatically by Sahodaya ERP Management Platform.</p>
                <p>Confidential — For official Sahodaya administration use only.</p>
            </td>
            <td class="sign-box">
                <div class="sign-title">Authorised Signatory</div>
                <div>{{ $sahodaya->name }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
