{{-- Standard heading used identically across every fest report in the Bulk Sheets combo
     (Chest Number List, Mark Entry/Sum Sheet, Result Declaration Sheet, Attendance Sheet,
     Timesheet) -- logo/org name/doc-title badge, then event title, then (when scoped to
     one item) that item's name/category/type/gender/participant count. Keeping this in
     one partial is what makes every report in a merged PDF look the same regardless of
     which one it is, instead of each having its own slightly different header layout.
     Usage: @include('partials.pdf-report-heading', [
         'orgName' => ..., 'logoSrc' => ..., 'docTitle' => ...,
         'eventTitle' => $event->title,
         'item' => $item ?? null,              // FestEventItem, omit when not item-scoped
         'categoryLabel' => $categoryLabel ?? null,
         'participantCount' => $count ?? null,  // int, optional
     ])
     A caller that has already built its own "Title · Category · Type · Gender · N
     participants"-shaped string (e.g. from flattened row data rather than a queried
     FestEventItem) can pass that directly as 'itemLine' instead of 'item' -- same
     styling, same field order, just skipping the FestEventItem-based construction
     below. --}}
@include('partials.pdf-branding-header', [
    'orgName' => $orgName ?? 'Sahodaya',
    'logoSrc' => $logoSrc ?? null,
    'docTitle' => $docTitle ?? null,
])
<div style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 2px;">{{ $eventTitle }}</div>
@if(!empty($item))
    @php
        $headingParts = array_filter([
            ($item->item_code ? "[{$item->item_code}] " : '').$item->title,
            $categoryLabel ?? null,
            \App\Support\FestTeamSquadRules::isMultiPerson($item->participant_type ?? null) ? 'Group' : 'Individual',
            \App\Support\FestSportsAgeGroup::genderLabel($item->gender ?? null),
        ]);
        if (!empty($participantCount)) {
            $headingParts[] = $participantCount.' participant'.((int) $participantCount === 1 ? '' : 's');
        }
    @endphp
    {{-- font-weight: bold (700), not 800 -- see the matching comment in
         pdf-branding-header.blade.php for why. --}}
    <div style="font-size: 13px; font-weight: bold; color: #0f172a;">{{ implode(' · ', $headingParts) }}</div>
@elseif(!empty($itemLine))
    <div style="font-size: 13px; font-weight: bold; color: #0f172a;">{{ $itemLine }}</div>
@endif
