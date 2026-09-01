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
<div class="id-card">
    <div class="card-header">
        @if(!empty($clusterLogoSrc))
            <img src="{{ $clusterLogoSrc }}" class="sahodaya-logo" alt="">
        @else
            <span></span>
        @endif

        <div class="branding">
            <div class="sahodaya-name">{{ $card['sahodaya_name'] ?? $clusterName }}</div>
            <div class="sahodaya-tagline">Festival {{ $passLabel }} ID</div>
        </div>

        <div class="event-branding">
            <div class="event-name">{{ $eventDisplayName }}</div>
        </div>
    </div>

    <div class="card-content">
        <div class="photo-column">
            <div class="photo-box">
                @if(!empty($card['photo_src']))
                    <img src="{{ $card['photo_src'] }}" class="student-photo" alt="" onerror="this.style.display='none'">
                @else
                    <div class="photo-fallback">{{ $card['initials'] ?? '' }}</div>
                @endif
            </div>
            <div class="participant-label">{{ $roleLabel }}</div>
        </div>

        <div class="student-details">
            <div class="student-name">{{ $card['name'] ?? '—' }}</div>
            <div class="school-name">{{ $schoolName }}</div>

            @if($isStaffOrVolunteer)
                <div class="meta-grid">
                    <div class="meta-box">
                        <span class="meta-label">{{ $audience === 'staff' ? 'Location' : 'Contact' }}</span>
                        <span class="meta-value">{{ $card['detail'] ?? '—' }}</span>
                    </div>
                    <div class="meta-box">
                        <span class="meta-label">Event</span>
                        <span class="meta-value">{{ $eventDisplayName }}</span>
                    </div>
                    <div class="meta-box">
                        <span class="meta-label">{{ $card['secondary_label'] ?? 'Info' }}</span>
                        <span class="meta-value category-value">{{ $card['secondary_value'] ?? '—' }}</span>
                    </div>
                    <div class="meta-box">
                        <span class="meta-label">{{ $idLabel }}</span>
                        <span class="meta-value">{{ $idNumber }}</span>
                    </div>
                </div>
            @else
                <div class="meta-grid">
                    <div class="meta-box">
                        <span class="meta-label">Venue</span>
                        <span class="meta-value">{{ $card['venue'] ?? '—' }}</span>
                    </div>
                    <div class="meta-box">
                        <span class="meta-label">Event Date</span>
                        <span class="meta-value">{{ $card['event_date'] ?? '—' }}</span>
                    </div>
                    <div class="meta-box">
                        <span class="meta-label">Category</span>
                        <span class="meta-value category-value">{{ $category }}</span>
                    </div>
                    <div class="meta-box">
                        <span class="meta-label">{{ $idLabel }}</span>
                        <span class="meta-value">{{ $idNumber }}</span>
                    </div>
                </div>

                <div class="items-section">
                    <div class="items-title">
                        <strong>Registered Items</strong>
                        <span class="item-count">{{ count($items) }}</span>
                    </div>
                    <ol class="item-list">
                        @foreach($items as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    </div>

    <div class="card-footer">
        <span class="footer-text">Official {{ $passLabel }} Pass</span>
        <div class="corner-decoration"></div>
    </div>
</div>
