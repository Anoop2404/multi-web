<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $exam->title }} — Class-Wise Fee & Due Report</title>
    <style>
        @page { margin: 15mm 12mm 15mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
        .header { margin-bottom: 15px; border-bottom: 2.5px solid #0f3d7a; padding-bottom: 10px; }
        .logo { max-height: 48px; float: right; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0 0 4px; }
        .subtitle { font-size: 11px; color: #475569; margin: 0; font-weight: bold; }
        .meta { font-size: 9px; color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        th { background-color: #0f3d7a; color: #ffffff; font-size: 9px; text-transform: uppercase; text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .tfoot-row { background-color: #f1f5f9; font-weight: bold; }
        .amount-due { color: #b91c1c; font-weight: bold; }
        .amount-paid { color: #15803d; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoSrc))
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <div class="title">{{ $exam->title }}</div>
        <div class="subtitle">Class-Wise Fee & Pending Due Report {{ $school ? '— '.$school->name : '' }}</div>
        <div class="meta">{{ $orgName }} · Generated on {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">Sl No</th>
                <th>Class / Roster</th>
                <th class="text-center">Registered Students</th>
                <th class="text-right">Fee Rate</th>
                <th class="text-right">Total Amount</th>
                <th class="text-right">Paid Amount</th>
                <th class="text-right">Pending Due Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($matrix['rows'] as $i => $row)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $row['class_name'] }}</td>
                    <td class="text-center">{{ $row['count'] }}</td>
                    <td class="text-right">₹{{ number_format($row['fee_rate'], 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($row['total_fee'], 2) }}</td>
                    <td class="text-right amount-paid">₹{{ number_format($row['paid'], 2) }}</td>
                    <td class="text-right amount-due">₹{{ number_format($row['due'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="2" class="text-right">GRAND TOTAL</td>
                <td class="text-center">{{ $matrix['grand_count'] }}</td>
                <td class="text-right">₹{{ number_format($matrix['fee_rate'], 2) }}</td>
                <td class="text-right font-bold">₹{{ number_format($matrix['grand_total_fee'], 2) }}</td>
                <td class="text-right amount-paid">₹{{ number_format($matrix['grand_paid'], 2) }}</td>
                <td class="text-right amount-due">₹{{ number_format($matrix['grand_due'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
