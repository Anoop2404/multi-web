@php
    $isTeam = ($card['card_type'] ?? 'individual') === 'team';
    $isEventPass = ($card['card_type'] ?? '') === 'event_participant';
    $isHeadPass = ($card['card_type'] ?? '') === 'head_participant';
    $roleClass = $card['role_class'] ?? 'student';
@endphp
<div class="pcard pcard--{{ $roleClass }}">
    <header class="pcard__header">
        <div class="pcard__brand">
            @if(!empty($clusterLogoSrc))
                <img src="{{ $clusterLogoSrc }}" alt="{{ $clusterName }}" class="pcard__logo">
            @else
                <div class="pcard__logo-fallback">{{ $clusterInitials ?? 'S' }}</div>
            @endif
            <div class="pcard__brand-text">
                <p class="pcard__cluster">{{ $clusterName }}</p>
                <p class="pcard__event">{{ $eventTitle }}</p>
            </div>
        </div>

        <div class="pcard__pass-ribbon">
            <span>{{ $isEventPass ? 'EVENT PASS' : 'ID CARD' }}</span>
        </div>
    </header>

    <div class="pcard__wave-separator">
        <svg viewBox="0 0 500 20" preserveAspectRatio="none">
            <path d="M0 0 C 150 18, 350 18, 500 0 L 500 20 L 0 20 Z" fill="#ffffff"/>
            <path d="M0 0 C 150 16, 350 16, 500 0" fill="none" stroke="#10b981" stroke-width="3"/>
        </svg>
    </div>

    <div class="pcard__body">
        <div class="pcard__portrait">
            @if(!empty($card['photo_src']))
                <img src="{{ $card['photo_src'] }}" alt="" class="pcard__photo">
            @else
                <div class="pcard__initials">{{ $card['initials'] ?? '?' }}</div>
            @endif
        </div>

        <div class="pcard__info-col">
            <p class="pcard__name">{{ $card['name'] }}</p>
            <table class="pcard__meta-table">
                <tr>
                    <td class="pcard__meta-icon">👤</td>
                    <td class="pcard__meta-label">Role</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['role_title'] ?? 'Participant' }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-icon">🏆</td>
                    <td class="pcard__meta-label">Event</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['event_name'] ?? $eventTitle }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-icon">📅</td>
                    <td class="pcard__meta-label">Date</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['event_date'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-icon">📍</td>
                    <td class="pcard__meta-label">Venue</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['venue'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-icon">🏫</td>
                    <td class="pcard__meta-label">Shodaya</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['sahodaya_name'] ?? ($clusterName ?? 'Kannur Sahodaya') }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-icon">🎯</td>
                    <td class="pcard__meta-label">Category</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['category'] ?? ($card['class_category'] ?? '—') }}</td>
                </tr>
            </table>
        </div>

        <div class="pcard__qr-col">
            @if(!empty($card['qr_src']))
                <img src="{{ $card['qr_src'] }}" alt="" class="pcard__qr">
                <span class="pcard__qr-label">SCAN TO VERIFY</span>
            @endif
        </div>
    </div>

    <footer class="pcard__footer">
        <div class="pcard__school-pill">
            <span class="pcard__school-icon">🏫</span>
            <span class="pcard__school-text">{{ $card['subtitle'] ?? ($card['school_name'] ?? '—') }}</span>
        </div>
        <div class="pcard__role-pill">
            <span>{{ $card['role_label'] ?? 'PARTICIPANT' }}</span>
        </div>
    </footer>
</div>
