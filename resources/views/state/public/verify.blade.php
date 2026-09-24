@extends('layouts.portal')

@section('title', 'Verify a State Kalotsav certificate')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:38rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <h1 class="portal-card-title">Verify a certificate</h1>
                <p class="portal-card-sub">Scan the QR on the certificate, or type the code printed beside it.</p>
            </div>
            <div class="portal-card-body">
                @include('state.public._styles')

                <form method="GET" action="/state/certificates/verify" style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
                    <input name="code" value="{{ $code }}" placeholder="Verification code" required
                           style="flex:1;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:.5rem;font-family:monospace;">
                    <button type="submit" style="padding:.55rem 1rem;border-radius:.5rem;border:0;background:#1e3a5f;color:#fff;font-weight:600;">Check</button>
                </form>

                @if ($result)
                    @if (! $result['found'])
                        <div style="border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:.6rem;padding:.85rem 1rem;">
                            <strong>Not found.</strong> {{ $result['message'] }}
                        </div>
                    @else
                        {{-- A stale or superseded certificate is reported as what it is. Presenting it as
                             "valid" would let a corrected result keep circulating on paper. --}}
                        @php $ok = $result['valid']; @endphp
                        <div style="border:1px solid {{ $ok ? '#bbf7d0' : '#fde68a' }};background:{{ $ok ? '#f0fdf4' : '#fffbeb' }};color:{{ $ok ? '#166534' : '#92400e' }};border-radius:.6rem;padding:.85rem 1rem;margin-bottom:1rem;">
                            <strong>{{ $ok ? 'Valid certificate.' : 'Needs attention.' }}</strong> {{ $result['message'] }}
                        </div>

                        @php $c = $result['certificate']; @endphp
                        <table class="state-table">
                            <tbody>
                                <tr><th style="width:9rem;">Certificate</th><td style="font-family:monospace;">{{ $c['number'] }}</td></tr>
                                <tr><th>Type</th><td>{{ $c['type'] }}</td></tr>
                                <tr><th>Recipient</th><td><strong>{{ $c['recipient'] }}</strong></td></tr>
                                <tr><th>School</th><td>{{ $c['school'] ?? '—' }}</td></tr>
                                <tr><th>Sahodaya</th><td>{{ $c['sahodaya'] ?? '—' }}</td></tr>
                                @if ($c['item'])<tr><th>Item</th><td>{{ $c['item'] }}</td></tr>@endif
                                @if ($c['position'])<tr><th>Position</th><td>{{ \App\Support\Ordinal::of($c['position']) }}</td></tr>@endif
                                @if ($c['grade'])<tr><th>Grade</th><td>{{ $c['grade'] }}</td></tr>@endif
                                <tr><th>Event</th><td>{{ $c['event'] ?? '—' }}</td></tr>
                                <tr><th>Issued</th><td>{{ $c['issued_on'] ?? '—' }}</td></tr>
                            </tbody>
                        </table>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
