@php
    $isTeam = ($card['card_type'] ?? 'individual') === 'team';
    $isEventPass = ($card['card_type'] ?? '') === 'event_participant';
    $isHeadPass = ($card['card_type'] ?? '') === 'head_participant';
    $roleClass = $card['role_class'] ?? 'student';
@endphp
<div class="pcard pcard--{{ $roleClass }}">
    <div class="pcard__header">
        <div class="pcard__brand-cell">
            <div style="display: table; width: 100%;">
                <div class="pcard__logo-cell">
                    @if(!empty($clusterLogoSrc))
                        <img src="{{ $clusterLogoSrc }}" alt="{{ $clusterName }}" class="pcard__logo">
                    @else
                        <div class="pcard__logo-fallback">{{ $clusterInitials ?? 'S' }}</div>
                    @endif
                </div>
                <div class="pcard__text-cell">
                    <div class="pcard__cluster">{{ $clusterName }}</div>
                    <div class="pcard__event">{{ $eventTitle }}</div>
                </div>
            </div>
        </div>

        <div class="pcard__ribbon-cell">
            <span class="pcard__pass-ribbon">{{ $isEventPass ? 'EVENT PASS' : ($isHeadPass ? 'HEAD PASS' : 'ID CARD') }}</span>
        </div>
    </div>

    <div class="pcard__divider"></div>

    <div class="pcard__body">
        <div class="pcard__portrait-cell">
            <div class="pcard__portrait">
                @if(!empty($card['photo_src']))
                    <img src="{{ $card['photo_src'] }}" alt="" class="pcard__photo">
                @else
                    <div class="pcard__initials">{{ $card['initials'] ?? '?' }}</div>
                @endif
            </div>
        </div>

        <div class="pcard__info-cell">
            <div class="pcard__name">{{ $card['name'] }}</div>
            <table class="pcard__meta-table">
                <tr>
                    <td class="pcard__meta-label">Role</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['role_title'] ?? 'Participant' }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-label">Event</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['event_name'] ?? $eventTitle }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-label">Date</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['event_date'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-label">Venue</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['venue'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-label">Shodaya</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['sahodaya_name'] ?? ($clusterName ?? 'Sahodaya') }}</td>
                </tr>
                <tr>
                    <td class="pcard__meta-label">Category</td>
                    <td class="pcard__meta-sep">:</td>
                    <td class="pcard__meta-val">{{ $card['category'] ?? ($card['class_category'] ?? '—') }}</td>
                </tr>
            </table>
        </div>

        <div class="pcard__qr-cell">
            @if(!empty($card['qr_src']))
                <img src="{{ $card['qr_src'] }}" alt="" class="pcard__qr">
                <span class="pcard__qr-label">SCAN TO VERIFY</span>
            @endif
        </div>
    </div>

    <div class="pcard__footer">
        <div class="pcard__school-cell">
            <div class="pcard__school-pill">
                <span class="pcard__school-text">{{ $card['subtitle'] ?? ($card['school_name'] ?? '—') }}</span>
            </div>
        </div>
        <div class="pcard__role-cell">
            <div class="pcard__role-pill">
                <span>{{ $card['role_label'] ?? 'PARTICIPANT' }}</span>
            </div>
        </div>
    </div>
</div>
