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
<div class="pass-card-pdf">
    <div class="pass-card-pdf__header">
        <div class="pass-card-pdf__logo-cell">
            @if(!empty($clusterLogoSrc))
                <img src="{{ $clusterLogoSrc }}" class="pass-card-pdf__logo" alt="">
            @endif
        </div>
        <div class="pass-card-pdf__brand-cell">
            <div class="pass-card-pdf__org-name">{{ $card['sahodaya_name'] ?? $clusterName }}</div>
            <div class="pass-card-pdf__tagline">Festival {{ $passLabel }} ID</div>
        </div>
        <div class="pass-card-pdf__event-cell">
            <div class="pass-card-pdf__event-name">{{ $eventDisplayName }}</div>
        </div>
    </div>

    <div class="pass-card-pdf__body">
        <div class="pass-card-pdf__photo-cell">
            <div class="pass-card-pdf__photo-box">
                @if(!empty($card['photo_src']))
                    <img src="{{ $card['photo_src'] }}" class="pass-card-pdf__photo" alt="">
                @else
                    <div class="pass-card-pdf__photo-fallback">{{ $card['initials'] ?? '' }}</div>
                @endif
            </div>
            <div class="pass-card-pdf__role-label">{{ $roleLabel }}</div>
        </div>

        <div class="pass-card-pdf__info-cell">
            <div class="pass-card-pdf__name">{{ $card['name'] ?? '—' }}</div>
            <div class="pass-card-pdf__school">{{ $schoolName }}</div>

            @if($isStaffOrVolunteer)
                <table class="pass-card-pdf__meta">
                    <tr>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">{{ $audience === 'staff' ? 'Location' : 'Contact' }}</span>
                            <span class="pass-card-pdf__meta-value">{{ $card['detail'] ?? '—' }}</span>
                        </td>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">Event</span>
                            <span class="pass-card-pdf__meta-value">{{ $eventDisplayName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">{{ $card['secondary_label'] ?? 'Info' }}</span>
                            <span class="pass-card-pdf__meta-value pass-card-pdf__meta-value--accent">{{ $card['secondary_value'] ?? '—' }}</span>
                        </td>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">{{ $idLabel }}</span>
                            <span class="pass-card-pdf__meta-value">{{ $idNumber }}</span>
                        </td>
                    </tr>
                </table>
            @else
                <table class="pass-card-pdf__meta">
                    <tr>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">Venue</span>
                            <span class="pass-card-pdf__meta-value">{{ $card['venue'] ?? '—' }}</span>
                        </td>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">Event Date</span>
                            <span class="pass-card-pdf__meta-value">{{ $card['event_date'] ?? '—' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">Category</span>
                            <span class="pass-card-pdf__meta-value pass-card-pdf__meta-value--accent">{{ $category }}</span>
                        </td>
                        <td class="pass-card-pdf__meta-box">
                            <span class="pass-card-pdf__meta-label">{{ $idLabel }}</span>
                            <span class="pass-card-pdf__meta-value">{{ $idNumber }}</span>
                        </td>
                    </tr>
                </table>

                <div class="pass-card-pdf__items">
                    <table class="pass-card-pdf__items-title"><tr>
                        <td><strong>Registered Items</strong></td>
                        <td class="pass-card-pdf__items-count-cell"><span class="pass-card-pdf__items-count">{{ count($items) }}</span></td>
                    </tr></table>
                    <table class="pass-card-pdf__items-table">
                        <tr>
                            <td class="pass-card-pdf__items-col">
                                <ol start="1">
                                    @foreach($itemsLeft as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ol>
                            </td>
                            <td class="pass-card-pdf__items-col">
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

    <div class="pass-card-pdf__footer">
        <span>Official {{ $passLabel }} Pass</span>
    </div>
</div>
