{{-- One item's batch sections -- shared between the single-item and bulk (all-items)
     reporting-batches print views, so both render identically.
     Usage: @include('fest.partials.reporting-batches-sections', ['sections' => ..., 'isGroup' => ...]) --}}
@forelse($sections as $section)
    {{-- batch-block wraps the heading and its table as one page-break-inside:avoid unit so a
         heading can never be stranded on one page with its rows starting on the next --
         page-break-after:avoid on the heading alone isn't reliably honored by either
         rendering engine once the table that follows it doesn't fit in the remaining
         space, so the block as a whole is what has to move to the next page together. --}}
    <div class="batch-block">
        <div class="batch-header">
            <div class="batch-title">{{ $section['label'] }}</div>
            <div class="batch-meta">
                {{ count($section['rows']) }} registration(s)
                @if($section['report_at'])
                    &bull; Reports: {{ \Illuminate\Support\Carbon::parse($section['report_at'])->format('d M Y, h:i A') }}
                @endif
            </div>
        </div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 40px;">Sl No</th>
                    <th>{{ $isGroup ? 'Team / Participant' : 'Name' }}</th>
                    <th>School</th>
                    <th style="width: 90px; text-align: center;">Sign</th>
                </tr>
            </thead>
            <tbody>
                @foreach($section['rows'] as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $row['name'] }}@if($row['is_team']) ({{ $row['member_count'] }})@endif
                        @if($row['is_team'] && $row['first_participant_name'])
                            <br><span style="color: #64748b; font-size: 9.5px;">{{ $row['first_participant_name'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['school'] }}@if(isset($row['distance_km'])) <span style="color: #64748b;">({{ $row['distance_km'] }} km)</span>@endif</td>
                    <td></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p style="color: #64748b;">No registrations for this item.</p>
@endforelse
