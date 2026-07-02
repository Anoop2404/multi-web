@php
    $isTeam = ($card['card_type'] ?? 'individual') === 'team';
    $isEventPass = ($card['card_type'] ?? '') === 'event_participant';
    $roleClass = $card['role_class'] ?? 'student';
    $itemLabel = $card['item_label'] ?? ($isEventPass ? null : ($card['detail'] ?? null));
@endphp
<div class="pcard pcard--{{ $roleClass }}{{ $isEventPass ? ' pcard--event-pass' : '' }}">
    <div class="pcard__stripe"></div>
    <div class="pcard__head">
        <div class="pcard__head-left">
            <p class="pcard__cluster">{{ $clusterName }}</p>
            <p class="pcard__event">{{ $eventTitle }}</p>
        </div>
        <div class="pcard__head-right">
            <span class="pcard__role">{{ $card['role_label'] ?? 'PARTICIPANT' }}</span>
            @if(!empty($card['qr_src']))
                <img src="{{ $card['qr_src'] }}" alt="" class="pcard__qr">
            @endif
        </div>
    </div>

    @if($isEventPass)
        <div class="pcard__pass-badge">Event participant pass</div>
    @elseif($itemLabel)
        <div class="pcard__item-badge">{{ $itemLabel }}</div>
    @endif

    @if($isTeam)
        <div class="pcard__body pcard__body--team">
            <p class="pcard__name">{{ $card['name'] }}</p>
            <p class="pcard__sub">{{ $card['subtitle'] ?? '' }}</p>
            <p class="pcard__detail">{{ $card['detail'] ?? '' }}</p>
            @if(!empty($card['schedule']))
                <p class="pcard__schedule">{{ $card['schedule'] }}</p>
            @endif
            <div class="pcard__members">
                @foreach(array_slice($card['members'] ?? [], 0, 6) as $member)
                    <div class="pcard__member">
                        {{ $member['name'] }} · {{ $member['fest_id'] }}
                        @if(!empty($member['chest'])) · {{ $member['chest'] }} @endif
                    </div>
                @endforeach
                @if(($card['member_count'] ?? 0) > 6)
                    <div class="pcard__member pcard__member--more">+ {{ ($card['member_count'] ?? 0) - 6 }} more</div>
                @endif
            </div>
        </div>
    @else
        <div class="pcard__body">
            <div class="pcard__avatar-wrap">
                @if(!empty($card['photo_src']))
                    <img src="{{ $card['photo_src'] }}" alt="" class="pcard__photo">
                @else
                    <div class="pcard__initials">{{ $card['initials'] ?? '?' }}</div>
                @endif
            </div>
            <div class="pcard__info">
                <p class="pcard__name">{{ $card['name'] }}</p>
                <p class="pcard__sub">{{ $card['subtitle'] ?? '' }}</p>
                @if($isEventPass && !empty($card['items']))
                    <ul class="pcard__items">
                        @foreach(array_slice($card['items'], 0, 4) as $itemTitle)
                            <li>{{ $itemTitle }}</li>
                        @endforeach
                        @if(($card['item_count'] ?? 0) > 4)
                            <li class="pcard__items-more">+ {{ ($card['item_count'] ?? 0) - 4 }} more item(s)</li>
                        @endif
                    </ul>
                @else
                    <p class="pcard__detail">{{ $card['detail'] ?? '' }}</p>
                @endif
                @if(!empty($card['schedule']))
                    <p class="pcard__schedule">{{ $card['schedule'] }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="pcard__ids">
        <div class="pcard__id-block">
            <span class="pcard__id-label">{{ $card['id_label'] ?? 'ID' }}</span>
            <span class="pcard__id-value">{{ $card['id_number'] ?? '—' }}</span>
        </div>
        <div class="pcard__id-block">
            <span class="pcard__id-label">{{ $card['secondary_label'] ?? '' }}</span>
            <span class="pcard__id-value pcard__id-value--sm">{{ $card['secondary_value'] ?? '—' }}</span>
        </div>
    </div>
    <div class="pcard__foot">{{ $card['footer'] ?? $eventTitle }}</div>
</div>
