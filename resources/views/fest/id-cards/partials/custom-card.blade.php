@php
    $card ??= [];
@endphp
<div class="card" style="width:{{ $cardWidthMm }}mm;height:{{ $cardHeightMm }}mm;">
    @if(!empty($backgroundUrl))
        <img src="{{ $backgroundUrl }}" alt="" class="card__bg">
    @endif

    @foreach($fields as $field)
        @php
            $type = $field['type'] ?? 'text';
            $source = $field['source'] ?? $field['key'] ?? null;
            $value = $source ? ($card[$source] ?? null) : null;
        @endphp

        @if($type === 'photo')
            @if(!empty($value))
                <img src="{{ $value }}" alt="" class="card__photo"
                     style="top:{{ $field['top'] ?? 8 }}%;left:{{ $field['left'] ?? 4 }}%;width:{{ $field['width'] ?? 22 }}%;height:{{ $field['height'] ?? 26 }}%;">
            @endif
        @elseif($type === 'qr')
            @if(!empty($value))
                <img src="{{ $value }}" alt="" class="card__qr"
                     style="top:{{ $field['top'] ?? 4 }}%;left:{{ $field['left'] ?? 82 }}%;width:{{ $field['width'] ?? 14 }}%;height:{{ $field['height'] ?? 14 }}%;">
            @endif
        @else
            @if($value !== null && $value !== '')
                <div class="card__field"
                     style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($field, ['font_size' => 9, 'font_family' => 'Arial']) }}">
                    {{ $value }}
                </div>
            @endif
        @endif
    @endforeach
</div>
