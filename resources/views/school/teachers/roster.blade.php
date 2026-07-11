<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Teacher Roster</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;font-size:10px;color:#555;margin-bottom:12px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:3px 4px;vertical-align:top}
th{background:#f1f5f9;font-size:8px;text-transform:uppercase}
</style>
</head><body>
<h2>{{ $school->name }}</h2>
<p class="meta">Teacher roster — generated {{ now()->format('d M Y') }}</p>
<table>
<thead><tr>
    <th>Name</th><th>Teacher ID</th><th>Employee Code</th><th>Teaching Type</th><th>Subjects</th><th>Mobile</th><th>Email</th><th>Status</th><th>Verification</th>
</tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $row['name'] }}</td>
    <td>{{ $row['login_code'] }}</td>
    <td>{{ $row['employee_code'] }}</td>
    <td>{{ $row['teaching_type'] }}</td>
    <td>{{ $row['subjects'] }}</td>
    <td>{{ $row['mobile'] }}</td>
    <td>{{ $row['email'] }}</td>
    <td>{{ $row['status'] }}</td>
    <td>{{ $row['verification'] }}</td>
</tr>
@empty
<tr><td colspan="9" style="text-align:center;padding:16px">No teachers match the filters.</td></tr>
@endforelse
</tbody></table>
</body></html>
