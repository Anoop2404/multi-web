@php
    $audience = $card['audience'] ?? 'student';
    $isStaffOrVolunteer = in_array($audience, ['staff', 'volunteer'], true);

    $items = $card['items'] ?? [];
    if ($items === [] && ! empty($card['members'])) {
        $items = collect($card['members'])->pluck('name')->filter()->values()->all();
    }
    if ($items === [] && ! $isStaffOrVolunteer) {
        $items = array_values(array_filter([$card['item_label'] ?? $card['detail'] ?? null]));
    }
    $items = array_slice($items, 0, 7);
    $half = (int) ceil(count($items) / 2);
    $itemsLeft = array_slice($items, 0, $half);
    $itemsRight = array_slice($items, $half);

    $schoolName = $card['subtitle'] ?? $card['school_name'] ?? '—';
    $category = $card['category'] ?? $card['class_category'] ?? '—';
    $idLabel = $card['id_label'] ?? 'Reg ID';
    $idNumber = $card['id_number'] ?? '—';
    // A phase (Zonal, Sub District, ...) is the specific competition round the
    // student actually registered under; the sheet-wide $eventTitle is often the
    // season hub an admin is bulk-printing from, which can span several phases at
    // once — so the phase name wins on the card face whenever one is known.
    $eventDisplayName = $card['phase_name'] ?? $eventTitle;
    $roleLabel = $card['role_title'] ?? ucfirst(strtolower($card['role_label'] ?? 'Participant'));
    $passLabel = $card['role_title'] ?? ucwords(strtolower($card['role_label'] ?? 'Participant'));
@endphp
<div class="pass-card">
    <div class="pass-card__header">
        <div class="pass-card__logo-cell">
            @if(!empty($clusterLogoSrc))
                <img src="{{ $clusterLogoSrc }}" class="pass-card__logo" alt="">
            @endif
        </div>
        <div class="pass-card__brand-cell">
            <div class="pass-card__org-name">{{ $card['sahodaya_name'] ?? $clusterName }}</div>
            <div class="pass-card__tagline">Festival {{ $passLabel }} ID</div>
        </div>
        <div class="pass-card__event-cell">
            <div class="pass-card__event-name">{{ $eventDisplayName }}</div>
            <div class="pass-card__card-no">#{{ $cardNumber }}</div>
        </div>
    </div>

    <div class="pass-card__body">
        <div class="pass-card__photo-cell">
            <div class="pass-card__photo-box">
                @if(!empty($card['photo_src']))
                    <img src="{{ $card['photo_src'] }}" class="pass-card__photo" alt="">
                @else
                    <div class="pass-card__photo-fallback">{{ $card['initials'] ?? '' }}</div>
                @endif
            </div>
            <div class="pass-card__role-label">{{ $roleLabel }}</div>
        </div>

        <div class="pass-card__info-cell">
            <div class="pass-card__name">{{ $card['name'] ?? '—' }}</div>
            <div class="pass-card__school">{{ $schoolName }}</div>

            @if($isStaffOrVolunteer)
                <table class="pass-card__meta">
                    <tr>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">{{ $audience === 'staff' ? 'Location' : 'Contact' }}</span>
                            <span class="pass-card__meta-value">{{ $card['detail'] ?? '—' }}</span>
                        </td>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">Event</span>
                            <span class="pass-card__meta-value">{{ $eventDisplayName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">{{ $card['secondary_label'] ?? 'Info' }}</span>
                            <span class="pass-card__meta-value pass-card__meta-value--accent">{{ $card['secondary_value'] ?? '—' }}</span>
                        </td>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">{{ $idLabel }}</span>
                            <span class="pass-card__meta-value">{{ $idNumber }}</span>
                        </td>
                    </tr>
                </table>
            @else
                <table class="pass-card__meta">
                    <tr>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">Venue</span>
                            <span class="pass-card__meta-value">{{ $card['venue'] ?? '—' }}</span>
                        </td>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">Event Date</span>
                            <span class="pass-card__meta-value">{{ $card['event_date'] ?? '—' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">Category</span>
                            <span class="pass-card__meta-value pass-card__meta-value--accent">{{ $category }}</span>
                        </td>
                        <td class="pass-card__meta-box">
                            <span class="pass-card__meta-label">{{ $idLabel }}</span>
                            <span class="pass-card__meta-value">{{ $idNumber }}</span>
                        </td>
                    </tr>
                </table>

                <div class="pass-card__items">
                    <div class="pass-card__items-title">
                        <strong>Registered Items</strong>
                        <span class="pass-card__items-count">{{ count($items) }}</span>
                    </div>
                    <table class="pass-card__items-table">
                        <tr>
                            <td class="pass-card__items-col">
                                <ol start="1">
                                    @foreach($itemsLeft as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ol>
                            </td>
                            <td class="pass-card__items-col">
                                <ol start="{{ $half + 1 }}">
                                    @foreach($itemsRight as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ol>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="pass-card__footer">
        <span>Official {{ $passLabel }} Pass</span>
    </div>
</div>
