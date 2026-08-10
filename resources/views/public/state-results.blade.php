@extends('layouts.portal')

@section('title', 'State Kalotsavam — Public Results')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:56rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">Official State Results Portal</p>
                <h1 class="portal-card-title">{{ $event?->name ?? 'Kerala State Kalotsavam 2026' }}</h1>
                <p class="portal-card-sub">Privacy-filtered official winner announcements and grade standings.</p>
            </div>
            <div class="portal-card-body">
                @if(empty($results))
                    <p class="portal-hint">No certified public results published yet.</p>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                            <thead>
                                <tr style="text-align:left;color:#64748b;border-bottom:2px solid #e2e8f0;">
                                    <th style="padding:.75rem .5rem;">Item</th>
                                    <th style="padding:.75rem .5rem;">Participant</th>
                                    <th style="padding:.75rem .5rem;">School</th>
                                    <th style="padding:.75rem .5rem;">Chest No</th>
                                    <th style="padding:.75rem .5rem;">Pos</th>
                                    <th style="padding:.75rem .5rem;">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $row)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:.625rem .5rem;font-weight:600;">{{ $row['item_code'] }}</td>
                                    <td style="padding:.625rem .5rem;">{{ $row['student_name'] }}</td>
                                    <td style="padding:.625rem .5rem;color:#475569;">{{ $row['school_name'] }}</td>
                                    <td style="padding:.625rem .5rem;font-family:monospace;">{{ $row['chest_number'] ?? '—' }}</td>
                                    <td style="padding:.625rem .5rem;font-weight:700;color:var(--navy-900);">{{ $row['position'] ?? '—' }}</td>
                                    <td style="padding:.625rem .5rem;"><span style="background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:4px;font-weight:600;">{{ $row['grade'] ?? '—' }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
