<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate school list</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #172033; }
        h1 { margin: 0 0 5px; font-size: 17px; }
        .event { margin: 0 0 5px; font-size: 13px; }
        .subtitle { margin: 0 0 16px; color: #566176; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #687487; padding: 9px 7px; text-align: left; }
        th { background: #eef2f7; font-weight: bold; }
        td { height: 25px; vertical-align: middle; word-wrap: break-word; }
        .serial { text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $orgName }}</h1>
    <p class="event">{{ $event->title }}</p>
    <p class="subtitle">Certificate school list</p>
    <table>
        <colgroup><col style="width:8%"><col style="width:52%"><col style="width:18%"><col style="width:22%"></colgroup>
        <thead><tr><th class="serial">Sl No</th><th>School Name</th><th>Status</th><th>Sign</th></tr></thead>
        <tbody>
            @foreach($schools as $school)
                <tr><td class="serial">{{ $loop->iteration }}</td><td>{{ $school->name }}</td><td></td><td></td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
