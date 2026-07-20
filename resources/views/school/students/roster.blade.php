<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Student Roster</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;font-size:10px;color:#555;margin-bottom:12px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:3px 4px;vertical-align:top}
th{background:#f1f5f9;font-size:8px;text-transform:uppercase}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
<h2>{{ $school->name }}</h2>
<p class="meta">Student roster — generated {{ now()->format('d M Y') }}</p>
<table>
<thead><tr>
    <th>Name</th><th>Student ID</th><th>Class</th><th>Gender</th><th>DOB</th><th>Email</th><th>Status</th><th>Verification</th>
</tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $row['name'] }}</td>
    <td>{{ $row['reg_no'] }}</td>
    <td>{{ $row['class'] }}</td>
    <td>{{ $row['gender'] }}</td>
    <td>{{ $row['dob'] }}</td>
    <td>{{ $row['parent_email'] }}</td>
    <td>{{ $row['status'] }}</td>
    <td>{{ $row['verification'] }}</td>
</tr>
@empty
<tr><td colspan="8" style="text-align:center;padding:16px">No students match the filters.</td></tr>
@endforelse
</tbody></table>
</body></html>
