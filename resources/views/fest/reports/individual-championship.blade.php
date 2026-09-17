<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Individual Championship</title>
<style>
@page { margin: 28px 32px; }
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#1e293b;margin:0}
.group{margin-top:18px;page-break-inside:avoid}
.group-title{background:#1d3557;color:#fff;font-size:12px;font-weight:bold;padding:8px 12px;border-radius:6px 6px 0 0}
table{width:100%;border-collapse:collapse}
th{background:#3d5a80;color:#fff;padding:7px 10px;font-size:10px;text-align:left;border:1px solid #3d5a80}
td{border:1px solid #dde6f2;padding:7px 10px;font-size:10.5px;vertical-align:middle}
td.num{text-align:right;font-weight:bold;color:#1d3557}
td.rank{font-weight:bold;color:#0f172a;width:36px}
tbody tr:nth-child(even){background:#f7f9fc}
.rank-1{color:#b45309}
.rank-2{color:#475569}
.rank-3{color:#92400e}
.photo{width:28px;height:28px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:8px;border:1px solid #cbd5e1}
.photo-fallback{display:inline-block;width:28px;height:28px;border-radius:50%;background:#e2e8f0;color:#475569;text-align:center;line-height:28px;font-size:11px;font-weight:bold;vertical-align:middle;margin-right:8px}
.empty{text-align:center;color:#64748b;margin-top:40px;font-size:12px}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center;margin:4px 0 2px;font-size:16px;color:#0f172a">{{ $event->title }} — Individual Championship</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin:0 0 4px">
    {{ ($combined ?? false) ? 'Combined across every phase · ' : '' }}Generated on {{ now()->format('d M Y, h:i A') }}
</p>

@forelse($groups as $group)
<div class="group">
    <div class="group-title">{{ $group['label'] }}</div>
    <table>
        <thead><tr><th style="width:36px">Rank</th><th>Student</th><th>School</th><th style="text-align:right;width:70px">Points</th></tr></thead>
        <tbody>
        @foreach($group['rows'] as $row)
            <tr>
                <td class="rank {{ $row['rank'] <= 3 ? 'rank-'.$row['rank'] : '' }}">#{{ $row['rank'] }}</td>
                <td>
                    @if($row['student']['photo'] ?? null)
                        <img src="{{ $row['student']['photo'] }}" class="photo" alt="">
                    @else
                        <span class="photo-fallback">{{ strtoupper(substr((string) $row['student']['name'], 0, 1)) }}</span>
                    @endif
                    {{ strtoupper((string) $row['student']['name']) }}
                </td>
                <td>{{ strtoupper((string) $row['school']) }}</td>
                <td class="num">{{ $row['points'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@empty
<p class="empty">No individual championship points recorded yet. Publish item results first — the standing is computed live from them.</p>
@endforelse

</body></html>
