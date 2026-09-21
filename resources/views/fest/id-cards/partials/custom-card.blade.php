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
        @elseif($type === 'item_row')
            @if($value !== null && $value !== '')
                @php $rowNum = (int) ($field['row'] ?? 1); @endphp
                <div class="card__item-text"
                     style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($field, ['font_size' => 9, 'font_family' => 'Arial', 'font_weight' => 'normal', 'color' => '#12345a']) }} height:{{ $field['height'] ?? 2.4 }}%;">
                    {{ $rowNum }}) {{ $value }}
                </div>
            @endif
        @elseif($type === 'shape')
            @php
                $bg = !empty($field['gradient_from']) && !empty($field['gradient_to'])
                    ? 'linear-gradient(to right, '.$field['gradient_from'].', '.$field['gradient_to'].')'
                    : ($field['color'] ?? '#DCEBFB');
            @endphp
            <div class="card__shape" style="
                top:{{ $field['top'] ?? 0 }}%; left:{{ $field['left'] ?? 0 }}%;
                width:{{ $field['width'] ?? 20 }}%; height:{{ $field['height'] ?? 10 }}%;
                border-radius:{{ $field['radius'] ?? 0 }}mm; background:{{ $bg }};
            "></div>
        @elseif($type === 'static_text')
            @if(!empty($field['text']))
                <div class="card__field"
                     style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($field, ['font_size' => 9, 'font_family' => 'Arial']) }}">
                    {{ $field['text'] }}
                </div>
            @endif
        @elseif($type === 'divider')
            @php $orientation = $field['orientation'] ?? 'vertical'; @endphp
            <div class="card__divider card__divider--{{ $orientation }}" style="
                top:{{ $field['top'] ?? 0 }}%; left:{{ $field['left'] ?? 0 }}%;
                @if($orientation === 'vertical') height:{{ $field['height'] ?? 10 }}%;
                @else width:{{ $field['width'] ?? 10 }}%; @endif
            "></div>
        @else
            @if($value !== null && $value !== '')
                @php $wrap = (bool) ($field['wrap'] ?? false); @endphp
                <div class="card__field{{ $wrap ? ' card__field--wrap' : '' }}"
                     style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($field, ['font_size' => 9, 'font_family' => 'Arial']) }} @if(isset($field['height'])) height:{{ $field['height'] }}%; @endif @if(isset($field['line_height'])) line-height:{{ $field['line_height'] }}; @endif">
                    @if($wrap)<span>{{ $value }}</span>@else{{ $value }}@endif
                </div>
            @endif
        @endif
    @endforeach
</div>
