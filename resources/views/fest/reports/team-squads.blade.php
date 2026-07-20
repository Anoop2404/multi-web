<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Team Squads</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h3{margin:12px 0 4px}table{width:100%;border-collapse:collapse;margin-bottom:12px}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Team / Group Squads</h2>
@forelse($rows as $row)
<h3>{{ $row['item_title'] }} — {{ strtoupper($row['school_name'] ?? 'School') }}</h3>
<table><thead><tr><th>Sl No</th><th>Name</th><th>Reg no</th><th>Role</th></tr></thead>
<tbody>
@foreach($row['members'] as $m)
<tr><td>{{ $loop->iteration }}</td><td>{{ $m['name'] ?? '—' }}</td><td>{{ $m['reg_no'] ?? '—' }}</td><td>{{ $m['role'] ?? 'performer' }}</td></tr>
@endforeach
</tbody></table>
@empty
<p>No team/group registrations.</p>
@endforelse
</body></html>
