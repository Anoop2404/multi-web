@php
    $isEventPass = ($card['card_type'] ?? '') === 'event_participant';
    $isHeadPass  = ($card['card_type'] ?? '') === 'head_participant';
    $roleClass   = $card['role_class'] ?? 'student';
    $photoSrc    = !empty($card['photo_src']) ? $card['photo_src'] : (!empty($card['photo_url']) ? $card['photo_url'] : null);
@endphp
<table class="pcard-pdf" cellpadding="0" cellspacing="0">
    <!-- Header -->
    <tr>
        <td class="pcard-pdf__header" valign="middle">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="75%" valign="middle" style="padding-left:4mm;">
                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td valign="middle">
                                    @if(!empty($clusterLogoSrc))
                                        <img src="{{ $clusterLogoSrc }}" alt="{{ $clusterName }}" class="pcard-pdf__logo">
                                    @else
                                        <div class="pcard-pdf__logo-fallback">{{ $clusterInitials ?? 'S' }}</div>
                                    @endif
                                </td>
                                <td valign="middle" style="padding-left:2.8mm;">
                                    <div class="pcard-pdf__cluster">{{ $clusterName }}</div>
                                    <div class="pcard-pdf__event">{{ $eventTitle }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="25%" valign="top" align="right" style="padding-right:4mm;">
                        <div class="pcard-pdf__pass-ribbon">{{ $isEventPass ? 'EVENT PASS' : ($isHeadPass ? 'HEAD PASS' : 'ID CARD') }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Wave Separator / Divider -->
    <tr>
        <td class="pcard-pdf__divider"></td>
    </tr>

    <!-- Body -->
    <tr>
        <td class="pcard-pdf__body" valign="top">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="26mm" valign="top">
                        <div class="pcard-pdf__portrait">
                            @if(!empty($photoSrc))
                                <img src="{{ $photoSrc }}" alt="" class="pcard-pdf__photo">
                            @else
                                <div class="pcard-pdf__initials">{{ $card['initials'] ?? '?' }}</div>
                            @endif
                        </div>
                    </td>
                    <td valign="top" style="padding-left: 3.5mm; padding-right: 1.5mm;">
                        <div class="pcard-pdf__name">{{ $card['name'] }}</div>
                        <table class="pcard-pdf__meta-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="pcard-pdf__meta-label">Role</td>
                                <td class="pcard-pdf__meta-sep">:</td>
                                <td class="pcard-pdf__meta-val">{{ $card['role_title'] ?? 'Participant' }}</td>
                            </tr>
                            <tr>
                                <td class="pcard-pdf__meta-label">Event</td>
                                <td class="pcard-pdf__meta-sep">:</td>
                                <td class="pcard-pdf__meta-val">{{ $card['event_name'] ?? $eventTitle }}</td>
                            </tr>
                            <tr>
                                <td class="pcard-pdf__meta-label">Date</td>
                                <td class="pcard-pdf__meta-sep">:</td>
                                <td class="pcard-pdf__meta-val">{{ $card['event_date'] ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="pcard-pdf__meta-label">Venue</td>
                                <td class="pcard-pdf__meta-sep">:</td>
                                <td class="pcard-pdf__meta-val">{{ $card['venue'] ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="pcard-pdf__meta-label">Sahodaya</td>
                                <td class="pcard-pdf__meta-sep">:</td>
                                <td class="pcard-pdf__meta-val">{{ $card['sahodaya_name'] ?? ($clusterName ?? 'Sahodaya') }}</td>
                            </tr>
                            <tr>
                                <td class="pcard-pdf__meta-label">Category</td>
                                <td class="pcard-pdf__meta-sep">:</td>
                                <td class="pcard-pdf__meta-val">{{ $card['category'] ?? ($card['class_category'] ?? '—') }}</td>
                            </tr>
                        </table>
                    </td>
                    <td width="20mm" valign="top" align="center">
                        @if(!empty($card['qr_src']))
                            <img src="{{ $card['qr_src'] }}" alt="" class="pcard-pdf__qr">
                            <div class="pcard-pdf__qr-label">SCAN TO VERIFY</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td class="pcard-pdf__footer" valign="middle">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="70%" valign="middle">
                        <div class="pcard-pdf__school-pill">
                            <span class="pcard-pdf__school-text">{{ $card['subtitle'] ?? ($card['school_name'] ?? '—') }}</span>
                        </div>
                    </td>
                    <td width="30%" valign="middle" align="right">
                        <div class="pcard-pdf__role-pill">
                            <span>{{ $card['role_label'] ?? 'PARTICIPANT' }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
