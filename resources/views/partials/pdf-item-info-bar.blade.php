{{-- Shared item info strip for PDF/print documents — Item / Category / Type (Individual or
     Group) / Gender, in one consistent row across Chest Numbers, Mark Entry, and Results
     sheets (the Attendance sheet has its own equivalent, built for its per-item-section
     layout).
     Usage: @include('partials.pdf-item-info-bar', [
         'item' => $item,               // FestEventItem, required
         'category' => $categoryLabel,  // pre-resolved label string, or null
     ]) --}}
@if(!empty($item))
    <div style="margin: 4px 0 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 2px 18px 2px 0; vertical-align: top;">
                    <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #64748b; font-weight: 700;">Item</div>
                    <div style="font-size: 12.5px; font-weight: 700; color: #0f172a;">{{ $item->item_code ? "[{$item->item_code}] " : '' }}{{ $item->title }}</div>
                </td>
                @if(!empty($category ?? null))
                    <td style="padding: 2px 18px 2px 0; vertical-align: top;">
                        <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #64748b; font-weight: 700;">Category</div>
                        <div style="font-size: 12.5px; font-weight: 700; color: #0f172a;">{{ $category }}</div>
                    </td>
                @endif
                <td style="padding: 2px 18px 2px 0; vertical-align: top;">
                    <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #64748b; font-weight: 700;">Type</div>
                    <div style="font-size: 12.5px; font-weight: 700; color: #0f172a;">{{ \App\Support\FestTeamSquadRules::isMultiPerson($item->participant_type) ? 'Group' : 'Individual' }}</div>
                </td>
                <td style="padding: 2px 0; vertical-align: top;">
                    <div style="font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #64748b; font-weight: 700;">Gender</div>
                    <div style="font-size: 12.5px; font-weight: 700; color: #0f172a;">{{ \App\Support\FestSportsAgeGroup::genderLabel($item->gender) ?? 'Open' }}</div>
                </td>
            </tr>
        </table>
    </div>
@endif
