<table class="grid">
    @foreach(array_chunk($pageCards, 2) as $row)
    <tr>
        @foreach($row as $card)
        <td>
            @include('fest.id-cards.partials.pass-card-pdf', [
                'card' => $card,
                'clusterName' => $clusterName,
                'clusterLogoSrc' => $clusterLogoSrc,
                'eventTitle' => $eventTitle,
            ])
        </td>
        @endforeach
        @if(count($row) === 1)<td></td>@endif
    </tr>
    @endforeach
</table>
