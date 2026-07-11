<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Merit List {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 12px; color: #555; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
<h1>{{ $sahodaya->name }} — Merit List</h1>
<h2>Academic year {{ $year }}@if($class) · Class {{ $class }}@endif</h2>
<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Student</th>
        <th>School</th>
        <th>Class</th>
        <th>Stream</th>
        <th>%</th>
        <th>Admission</th>
        <th>Roll</th>
    </tr>
    </thead>
    <tbody>
    @foreach($toppers as $i => $t)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $t->name }}</td>
            <td>{{ $schoolNames[$t->tenant_id] ?? '' }}</td>
            <td>{{ $t->boardResult?->class }}</td>
            <td>{{ $t->examStream?->label ?? $t->stream }}</td>
            <td>{{ number_format((float) $t->percentage, 2) }}</td>
            <td>{{ $t->admission_no }}</td>
            <td>{{ $t->roll_no }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
