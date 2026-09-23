<div class="a4-sheet">
    @foreach($pageCards as $card)
        @include('fest.id-cards.partials.pass-card', [
            'card' => $card,
            'clusterName' => $clusterName,
            'clusterLogoSrc' => $clusterLogoSrc,
            'eventTitle' => $eventTitle,
            'programLabel' => $programLabel ?? null,
            'isSports' => $isSports ?? false,
        ])
    @endforeach
</div>
