<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Chest Numbers — {{ $event->title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 1.5rem; color: #111; }
        h1 { font-size: 1.25rem; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background: #f3f4f6; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Print</button>
    <h1>{{ $event->title }} — Chest Numbers</h1>
    <table>
        <thead>
            <tr><th>Chest No</th><th>Name</th><th>Item</th><th>School</th></tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td><strong>{{ $row['chest_no'] }}</strong></td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['item'] }}</td>
                <td>{{ $row['school'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
