@php
    $isTeam = ($card['card_type'] ?? 'individual') === 'team';
    $isEventPass = ($card['card_type'] ?? '') === 'event_participant';
    $isHeadPass = ($card['card_type'] ?? '') === 'head_participant';
    $roleClass = $card['role_class'] ?? 'student';
    $showItemsList = ($isEventPass || $isHeadPass) && ! empty($card['items']);
    $scheduleOnly = ! empty($card['schedule']) ? $card['schedule'] : null;
    $discipline = match (true) {
        $isEventPass => 'Event pass',
        $isHeadPass && ! empty($card['head_label']) => $card['head_label'],
        ! empty($card['head_label']) => $card['head_label'],
        ! empty($card['item_label']) => $card['item_label'],
        default => null,
    };
    $itemLine = $showItemsList ? null : ($card['detail'] ?? ($card['item_label'] ?? null));
    if ($itemLine !== null && $discipline !== null && mb_strtolower($itemLine) === mb_strtolower($discipline)) {
        $itemLine = null;
    }
@endphp
<div class="pcard pcard--{{ $roleClass }}{{ $isEventPass ? ' pcard--event-pass' : '' }}{{ $isHeadPass ? ' pcard--head-pass' : '' }}">
    <div class="pcard__accent"></div>

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
        @if(!empty($card['qr_src']))
            <img src="{{ $card['qr_src'] }}" alt="" class="pcard__qr">
        @endif
    </header>

    @if($discipline)
        <div class="pcard__discipline">
            <span class="pcard__discipline-text">{{ $discipline }}</span>
        </div>
    @endif

    @if($isTeam)
        <div class="pcard__body pcard__body--team">
            <div class="pcard__team-head">
                <p class="pcard__name">{{ $card['name'] }}</p>
                <p class="pcard__school">{{ $card['subtitle'] ?? '' }}</p>
                @if(!empty($card['detail']))
                    <p class="pcard__tag">{{ $card['detail'] }}</p>
                @endif
            </div>
            <div class="pcard__members">
                @foreach(array_slice($card['members'] ?? [], 0, 5) as $member)
                    <div class="pcard__member">
                        <span class="pcard__member-name">{{ $member['name'] }}</span>
                        <span class="pcard__member-meta">{{ $member['fest_id'] }}</span>
                    </div>
                @endforeach
                @if(($card['member_count'] ?? 0) > 5)
                    <div class="pcard__member pcard__member--more">+ {{ ($card['member_count'] ?? 0) - 5 }} more</div>
                @endif
            </div>
        </div>
    @else
        <div class="pcard__body">
            <div class="pcard__portrait">
                @if(!empty($card['photo_src']))
                    <img src="{{ $card['photo_src'] }}" alt="" class="pcard__photo">
                @else
                    <div class="pcard__initials">{{ $card['initials'] ?? '?' }}</div>
                @endif
            </div>

            <div class="pcard__info">
                <p class="pcard__name">{{ $card['name'] }}</p>
                <p class="pcard__school">{{ $card['subtitle'] ?? '' }}</p>

                @if($showItemsList)
                    <ul class="pcard__items">
                        @foreach(array_slice($card['items'], 0, 4) as $itemTitle)
                            <li>{{ $itemTitle }}</li>
                        @endforeach
                        @if(($card['item_count'] ?? 0) > 4)
                            <li class="pcard__items-more">+ {{ ($card['item_count'] ?? 0) - 4 }} more</li>
                        @endif
                    </ul>
                @elseif($itemLine)
                    <p class="pcard__tag">{{ $itemLine }}</p>
                @endif
            </div>
        </div>
    @endif

    <footer class="pcard__footer">
        <div class="pcard__footer-id">
            <span class="pcard__footer-label">{{ $card['id_label'] ?? 'Fest ID' }}</span>
            <span class="pcard__footer-value">{{ $card['id_number'] ?? '—' }}</span>
        </div>
        <span class="pcard__role">{{ $card['role_label'] ?? 'PARTICIPANT' }}</span>
        @if($isHeadPass && ($card['item_count'] ?? 0) > 0)
            <span class="pcard__footer-meta">{{ $card['item_count'] }} item{{ ($card['item_count'] ?? 0) === 1 ? '' : 's' }}</span>
        @endif
        @if($scheduleOnly)
            <span class="pcard__footer-schedule">{{ $scheduleOnly }}</span>
        @endif
    </footer>
</div>
