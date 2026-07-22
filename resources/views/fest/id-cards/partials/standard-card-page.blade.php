<table class="grid">
    @foreach(array_chunk($pageCards, 2) as $row)
    <tr>
        @foreach($row as $card)
        <td>
            <div class="card">
                <div class="card__head card__head--{{ $card['role_class'] }}">
                    <span class="card__org">{{ $clusterName }}</span>
                    <span class="card__role">{{ $card['role_label'] }}</span>
                    @if(!empty($card['qr_src']))
                    <span class="card__qr-head"><img src="{{ $card['qr_src'] }}" alt=""></span>
                    @endif
                </div>
                @if(($card['card_type'] ?? 'individual') === 'team')
                <div class="card__body">
                    <div class="card__info" style="display:block;padding-left:2.5mm;">
                        <div class="card__name">
                            {{ $card['name'] }}
                            @if(!empty($card['chest_number']))
                            <span style="font-weight:700;">· Chest #{{ $card['chest_number'] }}</span>
                            @endif
                        </div>
                        <div class="card__sub">{{ $card['subtitle'] }}</div>
                        <div class="card__detail">{{ $card['detail'] }}</div>
                        @if(!empty($card['schedule']))
                        <div class="card__detail" style="margin-top:0.8mm;">{{ $card['schedule'] }}</div>
                        @endif
                        <div class="card__members">
                            @foreach(array_slice($card['members'] ?? [], 0, 7) as $member)
                            <div class="card__member">
                                {{ $member['name'] }}
                                · {{ $member['fest_id'] }}
                            </div>
                            @endforeach
                            @if(($card['member_count'] ?? 0) > 7)
                            <div class="card__member card__member-role">+ {{ ($card['member_count'] ?? 0) - 7 }} more member(s)</div>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="card__body">
                    <div class="card__avatar">
                        @if(!empty($card['photo_src']))
                            <img src="{{ $card['photo_src'] }}" alt="" class="card__photo">
                        @else
                            <div class="card__avatar-inner">{{ $card['initials'] }}</div>
                        @endif
                    </div>
                    <div class="card__info">
                        <div class="card__name">{{ $card['name'] }}</div>
                        <div class="card__sub">{{ $card['subtitle'] }}</div>
                        <div class="card__detail">{{ $card['detail'] }}</div>
                        @if(!empty($card['schedule']))
                        <div class="card__detail" style="margin-top:0.8mm;">{{ $card['schedule'] }}</div>
                        @endif
                    </div>
                </div>
                @endif
                <div class="card__ids">
                    <div class="card__id-block">
                        <div class="card__id-label">{{ $card['id_label'] }}</div>
                        <div class="card__id-value">{{ $card['id_number'] }}</div>
                    </div>
                    <div class="card__id-block">
                        <div class="card__id-label">{{ $card['secondary_label'] }}</div>
                        <div class="card__id-value" style="font-size:8px;">{{ $card['secondary_value'] }}</div>
                    </div>
                </div>
                <div class="card__foot">{{ $card['footer'] }}</div>
            </div>
        </td>
        @endforeach
        @if(count($row) === 1)<td></td>@endif
    </tr>
    @endforeach
</table>
