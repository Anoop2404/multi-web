<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $exam->title }} — Class-Wise Fee & Due Report</title>
    <style>
        @page { margin: 15mm 12mm 15mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        .header { margin-bottom: 14px; border-bottom: 2.5px solid #0f3d7a; padding-bottom: 10px; }
        .logo { max-height: 48px; float: right; }
        .title { font-size: 17px; font-weight: bold; color: #0f172a; margin: 0 0 4px; }
        .subtitle { font-size: 12px; color: #475569; margin: 0; font-weight: bold; }
        .meta { font-size: 10.5px; color: #64748b; margin-top: 4px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; }
        .summary .lbl { font-size: 9px; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 2px; }
        .summary .val { font-size: 13px; font-weight: bold; color: #0f172a; }
        .class-section { page-break-after: always; break-after: page; margin-bottom: 14px; }
        .class-section:last-child { page-break-after: avoid; break-after: avoid; }
        .class-heading { background-color: #0f3d7a; color: #fff; font-size: 11px; font-weight: bold; padding: 5px 8px; margin-top: 12px; }
        .class-heading:first-of-type { margin-top: 0; }
        table.roster { width: 100%; border-collapse: collapse; }
        table.roster thead { display: table-header-group; }
        table.roster tr { page-break-inside: avoid; break-inside: avoid; }
        table.roster th, table.roster td { border: 1px solid #cbd5e1; padding: 4px 7px; }
        table.roster th { background-color: #eef2f7; font-size: 9.5px; text-transform: uppercase; text-align: left; color: #334155; }
        table.roster td { font-size: 10.5px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .status-paid { color: #15803d; font-weight: bold; }
        .status-unpaid { color: #b91c1c; font-weight: bold; }
        .class-subtotal td { background-color: #f1f5f9; font-weight: bold; }
        .grand-total { width: 100%; border-collapse: collapse; margin-top: 14px; page-break-inside: avoid; break-inside: avoid; }
        .grand-total td { border: 1px solid #cbd5e1; padding: 7px 8px; font-size: 11.5px; }
        .grand-total .lbl { text-align: right; font-weight: bold; width: 70%; }
        .grand-total .val { text-align: right; font-weight: bold; width: 30%; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoSrc))
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <div class="title">{{ $exam->title }}</div>
        <div class="subtitle">
            Class-Wise Fee & Pending Due Report
            {{ $school ? '— '.$school->name : '' }}
            @if($selectedClass && strtolower(trim($selectedClass)) !== 'all')
                — Class {{ $selectedClass }}
            @endif
        </div>
        <div class="meta">{{ $orgName }} · Generated on {{ $generatedAt }}</div>
    </div>

    <table class="summary">
        <tr>
            <td style="width: 25%;">
                <span class="lbl">Total Students</span>
                <span class="val">{{ $totalCount }}</span>
            </td>
            <td style="width: 25%;">
                <span class="lbl">Total Fee</span>
                <span class="val">₹{{ number_format($totalFee, 2) }}</span>
            </td>
            <td style="width: 25%;">
                <span class="lbl">Paid</span>
                <span class="val status-paid">₹{{ number_format($totalPaid, 2) }}</span>
            </td>
            <td style="width: 25%;">
                <span class="lbl">Pending Due</span>
                <span class="val status-unpaid">₹{{ number_format($totalUnpaid, 2) }}</span>
            </td>
        </tr>
    </table>

    @forelse($groupedRows as $className => $rows)
        <div class="class-section">
            <div class="class-heading">{{ $className }} ({{ count($rows) }} student{{ count($rows) === 1 ? '' : 's' }})</div>
            <table class="roster">
                <thead>
                    <tr>
                        <th style="width: 28px;" class="text-center">Sl No</th>
                        <th>Student Name</th>
                        <th>Adm No / Reg No</th>
                        @if(!$school)
                            <th>School</th>
                        @endif
                        <th class="text-right">Fee Amount</th>
                        <th class="text-center">Status</th>
                        <th>Receipt No</th>
                    </tr>
                </thead>
                <tbody>
                    @php($classFee = 0)
                    @php($classPaid = 0)
                    @foreach($rows as $i => $row)
                        @php($classFee += $row['fee_amount'])
                        @if($row['payment_status'] === 'Paid')
                            @php($classPaid += $row['fee_amount'])
                        @endif
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td class="font-bold">{{ $row['student_name'] }}</td>
                            <td>{{ $row['admission_number'] }} / {{ $row['reg_no'] }}</td>
                            @if(!$school)
                                <td>{{ $row['school_name'] }}</td>
                            @endif
                            <td class="text-right">₹{{ number_format($row['fee_amount'], 2) }}</td>
                            <td class="text-center {{ $row['payment_status'] === 'Paid' ? 'status-paid' : 'status-unpaid' }}">{{ $row['payment_status'] }}</td>
                            <td>{{ $row['receipt_no'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="class-subtotal">
                        <td colspan="{{ $school ? 3 : 4 }}" class="text-right">Class Total</td>
                        <td class="text-right">₹{{ number_format($classFee, 2) }}</td>
                        <td class="text-center">{{ number_format($classPaid, 2) === number_format($classFee, 2) ? 'All Paid' : '' }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; color: #94a3b8; padding: 20px 0;">No registrations found for this filter.</p>
    @endforelse

    <table class="grand-total">
        <tr>
            <td class="lbl">Grand Total ({{ $totalCount }} students)</td>
            <td class="val">₹{{ number_format($totalFee, 2) }}</td>
        </tr>
        <tr>
            <td class="lbl status-paid">Total Paid</td>
            <td class="val status-paid">₹{{ number_format($totalPaid, 2) }}</td>
        </tr>
        <tr>
            <td class="lbl status-unpaid">Total Pending Due</td>
            <td class="val status-unpaid">₹{{ number_format($totalUnpaid, 2) }}</td>
        </tr>
    </table>
</body>
</html>
