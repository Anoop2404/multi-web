@extends('layouts.portal')

@section('title', $sahodaya->name . ' — moved to the platform')

{{--
    Shown in place of the outside-Sahodaya roster once this Sahodaya has been promoted to a tenant
    of its own (docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md §5.3).

    The access code deliberately keeps working rather than 404ing: it has already gone out in
    circulars and on printed sheets, and a dead link tells a coordinator nothing. This page is the
    redirect — it names where they go now and why their old roster is not here.
--}}

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:40rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">
                    {{ $sahodaya->program->title ?? 'State Kalolsavam' }}
                </p>
                <h1 class="portal-card-title">{{ $sahodaya->name }}</h1>
                <p class="portal-card-sub">This Sahodaya now runs on the platform.</p>
            </div>
            <div class="portal-card-body">
                <p style="margin-bottom:1rem;">
                    Your Sahodaya has its own portal now, where you conduct your Kalotsav and send
                    winners to the State directly. Entries added on this page would not reach the
                    State, so this roster is closed.
                </p>

                @if($url)
                    <p style="margin-bottom:1.25rem;">
                        <a class="portal-btn portal-btn-primary" href="{{ $url }}">Go to {{ $sahodaya->name }}</a>
                    </p>
                    <p class="portal-card-sub" style="word-break:break-all;">{{ $url }}</p>
                @else
                    <div class="portal-alert portal-alert-warning">
                        Your new portal address has not been published yet. Contact the State
                        Kalolsavam office and they will send it to you.
                    </div>
                @endif

                <hr style="margin:1.5rem 0;border:0;border-top:1px solid rgba(0,0,0,.08);">

                <p class="portal-card-sub">
                    Don't have a login yet? Contact the State Kalolsavam office — they issue the
                    Sahodaya administrator account, and you create your schools' logins from there.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
