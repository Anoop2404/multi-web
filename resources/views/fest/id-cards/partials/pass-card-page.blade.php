<table class="grid">
    @foreach(array_chunk($pageCards, 2) as $rowIndex => $row)
    <tr>
        @foreach($row as $colIndex => $card)
        <td>
            @include('fest.id-cards.partials.pass-card', [
                'card' => $card,
                'clusterName' => $clusterName,
                'clusterLogoSrc' => $clusterLogoSrc,
                'eventTitle' => $eventTitle,
                'cardNumber' => str_pad((string) ($pageOffset + $rowIndex * 2 + $colIndex + 1), 2, '0', STR_PAD_LEFT),
            ])
        </td>
        @endforeach
        @if(count($row) === 1)<td></td>@endif
    </tr>
    @endforeach
</table>
