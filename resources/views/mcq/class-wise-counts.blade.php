<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $exam->title }} — School & Class-wise Registration Counts</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 15px; }
        .header { margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .logo { max-height: 40px; float: right; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0 0 4px; }
        .subtitle { font-size: 11px; color: #64748b; margin: 0; }
        .meta { font-size: 9px; color: #94a3b8; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        th { background-color: #f8fafc; color: #334155; font-size: 9px; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .tfoot-row { background-color: #f1f5f9; font-weight: bold; }
        .grand-total { background-color: #dcfce7; color: #166534; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoSrc))
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <div class="title">{{ $exam->title }}</div>
        <div class="subtitle">School & Class-wise Student Registration Report</div>
        <div class="meta">{{ $orgName }} · Generated on {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">Sl No</th>
                <th>School Name</th>
                @foreach($matrix['classes'] as $cls)
                    <th class="text-center">{{ $cls }}</th>
                @endforeach
                <th class="text-center font-bold">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($matrix['schools'] as $i => $school)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ strtoupper($school['school_name']) }}</td>
                    @foreach($matrix['classes'] as $cls)
                        <td class="text-center">{{ $school['counts'][$cls] ?? 0 }}</td>
                    @endforeach
                    <td class="text-center font-bold">{{ $school['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="2" class="text-right">TOTAL ALL SCHOOLS</td>
                @foreach($matrix['classes'] as $cls)
                    <td class="text-center">{{ $matrix['totals'][$cls] ?? 0 }}</td>
                @endforeach
                <td class="text-center grand-total">{{ $matrix['grand_total'] }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
