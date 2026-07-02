@extends('layouts.portal')

@section('title', $tenant->name)

@section('content')
@php $portalCta = $portalCta ?? []; @endphp
<div class="portal-wrap">
    <div class="portal-page">
        <div class="portal-shell">

            <div class="portal-brand">
                @include('partials.portal-brand', [
                    'tenant'    => $tenant,
                    'logoUrl'   => $logoUrl ?? null,
                    'eyebrow'   => $eyebrow ?? null,
                    'tagline'   => $tagline ?? null,
                    'motto'     => $motto ?? null,
                    'phone'     => $phone ?? null,
                    'email'     => $email ?? null,
                    'defaultTagline' => 'Membership registration portal for CBSE-affiliated member schools.',
                ])
            </div>

            <div class="portal-panel">
                <div class="portal-panel-intro">
                    <p class="portal-panel-heading">Get Started</p>
                    <h2 class="portal-panel-title">Welcome</h2>
                    <p class="portal-panel-sub">Register your school as a new member, or sign in to register students for events.</p>
                </div>

                <div class="portal-actions">
                    @if($isSahodaya)
                    <a href="{{ $portalCta['register_url'] ?? '/school-register' }}" class="portal-action portal-action-primary">
                        <div class="portal-action-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h6m-6 4h6m-6 4h6"/></svg>
                        </div>
                        <span class="portal-action-body">
                            <span class="portal-action-title">{{ $portalCta['register_label'] ?? 'School Registration' }}</span>
                            <span class="portal-action-desc">Apply for Sahodaya membership</span>
                        </span>
                        <span class="portal-action-arrow">→</span>
                    </a>
                    @endif

                    <a href="{{ $portalCta['login_url'] ?? '/school-login' }}" class="portal-action portal-action-secondary">
                        <div class="portal-action-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </div>
                        <span class="portal-action-body">
                            <span class="portal-action-title">{{ $portalCta['login_label'] ?? 'School Login' }}</span>
                            <span class="portal-action-desc">Register students for Kalotsav, sports &amp; more</span>
                        </span>
                        <span class="portal-action-arrow">→</span>
                    </a>

                    @if($isSahodaya)
                    <a href="{{ $portalCta['admin_login_url'] ?? '/login' }}" class="portal-action portal-action-ghost">
                        <span class="portal-action-body">
                            <span class="portal-action-title">{{ $portalCta['admin_login_label'] ?? 'Sahodaya Admin Login' }}</span>
                        </span>
                        <span class="portal-action-arrow">→</span>
                    </a>
                    @endif
                </div>
            </div>

        </div>

        <p class="portal-footer-note">CBSE Sahodaya School Complex · Membership Portal</p>
    </div>
</div>
@endsection
