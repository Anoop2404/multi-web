@php
    $pageCards ??= [];
@endphp
@if($gridLayout ?? null)
    <div class="die-page" style="height: {{ $pageHeightMm ?? 297 }}mm;">
        @foreach($pageCards as $i => $card)
            @php
                $col = $i % $gridLayout['cols'];
                $row = intdiv($i, $gridLayout['cols']);
                $centerX = $gridLayout['first_col_center_mm'] + $col * $gridLayout['col_pitch_mm'];
                $centerY = $gridLayout['first_row_center_mm'] + $row * $gridLayout['row_pitch_mm'];
                $left = $centerX - ($cardWidthMm ?? 96) / 2;
                $top = $centerY - ($cardHeightMm ?? 72) / 2;
            @endphp
            <div class="die-card-slot" style="left: {{ $left }}mm; top: {{ $top }}mm;">
                @include('fest.id-cards.partials.custom-card', ['card' => $card, 'backgroundUrl' => $backgroundUrl ?? null, 'fields' => $fields ?? [], 'cardWidthMm' => $cardWidthMm ?? 96, 'cardHeightMm' => $cardHeightMm ?? 72])
            </div>
        @endforeach
    </div>
@else
    <table class="grid">
        @foreach(array_chunk($pageCards, 2) as $row)
        <tr>
            @foreach($row as $card)
            <td>@include('fest.id-cards.partials.custom-card', ['card' => $card, 'backgroundUrl' => $backgroundUrl ?? null, 'fields' => $fields ?? [], 'cardWidthMm' => $cardWidthMm ?? 96, 'cardHeightMm' => $cardHeightMm ?? 72])</td>
            @endforeach
            @if(count($row) === 1)<td></td>@endif
        </tr>
        @endforeach
    </table>
@endif
