@php
    $card ??= [];
@endphp
<div class="card" style="width:{{ $cardWidthMm }}mm;height:{{ $cardHeightMm }}mm;">
    @if(!empty($backgroundUrl))
        <div class="card__bg"></div>
    @endif

    @foreach($fields as $field)
        @php
            $type = $field['type'] ?? 'text';
            $source = $field['source'] ?? $field['key'] ?? null;
            $value = $source ? ($card[$source] ?? null) : null;
            if ($value === null || $value === '') {
                if ($source === 'subtitle') {
                    $value = $card['school_name'] ?? null;
                } elseif ($source === 'id_number') {
                    $value = $card['roll_no'] ?? $card['student_seq_id'] ?? null;
                } elseif ($source === 'student_id') {
                    $value = $card['student_reg_no'] ?? null;
                }
            }
            if ($type === 'text' && !empty($field['text_format'])) {
                $value = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($card) {
                    $replacement = $card[$matches[1]] ?? '';

                    return is_scalar($replacement) ? (string) $replacement : '';
                }, (string) $field['text_format']);
            }
        @endphp

        @if($type === 'photo')
            @php $photoVal = $value ?: ($card['photo_src'] ?? $card['photo_url'] ?? null); @endphp
            @if(!empty($photoVal))
                <img src="{{ $photoVal }}" alt="" class="card__photo"
                     style="top:{{ $field['top'] ?? 8 }}%;left:{{ $field['left'] ?? 4 }}%;width:{{ $field['width'] ?? 22 }}%;height:{{ $field['height'] ?? 26 }}%;@if(!empty($field['rotation'])) transform:rotate({{ (float) $field['rotation'] }}deg);transform-origin:center center;@endif">
            @endif
        @elseif($type === 'qr')
            @php $qrVal = $value ?: ($card['qr_src'] ?? null); @endphp
            @if(!empty($qrVal))
                <img src="{{ $qrVal }}" alt="" class="card__qr"
                     style="top:{{ $field['top'] ?? 4 }}%;left:{{ $field['left'] ?? 82 }}%;width:{{ $field['width'] ?? 14 }}%;height:{{ $field['height'] ?? 14 }}%;@if(!empty($field['rotation'])) transform:rotate({{ (float) $field['rotation'] }}deg);transform-origin:center center;@endif">
            @endif
        @elseif($type === 'item_list')
            @php
                $maxItems = max(1, min(7, (int) ($field['max_items'] ?? 7)));
                $columns = max(1, min(2, (int) ($field['columns'] ?? 2)));
                $items = collect(is_array($value) ? $value : [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->take($maxItems)
                    ->values();
                // Fill across each row: 1 | 2, then 3 | 4, then 5 | 6, then 7.
                $rowCount = max(1, (int) ceil($maxItems / $columns));
            @endphp
            @if($items->isNotEmpty())
                <div class="card__item-list"
                     style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($field, ['font_size' => 10, 'font_family' => 'Arial', 'font_weight' => 'normal', 'color' => '#ffffff']) }} height:{{ $field['height'] ?? 10.6 }}%; @if(isset($field['line_height'])) line-height:{{ $field['line_height'] }}; @endif">
                    <table aria-hidden="true">
                        <tbody>
                        @for($row = 0; $row < $rowCount; $row++)
                            <tr style="height:{{ 100 / $rowCount }}%;">
                                @for($column = 0; $column < $columns; $column++)
                                    @php $itemIndex = ($row * $columns) + $column; @endphp
                                    <td style="width:{{ 100 / $columns }}%;">
                                        @if($items->has($itemIndex))
                                            <div class="card__item-list-row">{{ $itemIndex + 1 }}) {{ $items->get($itemIndex) }}</div>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
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
                $hasGradient = !empty($field['gradient_from']) && !empty($field['gradient_to']);
                // Dompdf does not paint CSS gradients. Keep a solid fallback behind the
                // browser gradient so ribbons remain visible in downloaded PDFs.
                $solidBg = $field['color'] ?? $field['gradient_to'] ?? $field['gradient_from'] ?? '#DCEBFB';
                $gradId = 'g_'.substr(md5(($field['gradient_from'] ?? '').($field['gradient_to'] ?? '').($field['top'] ?? '').($field['left'] ?? '')), 0, 8);
                $radiusMm = (float) ($field['radius'] ?? 0);
            @endphp
            <div class="card__shape" style="
                top:{{ $field['top'] ?? 0 }}%; left:{{ $field['left'] ?? 0 }}%;
                width:{{ $field['width'] ?? 20 }}%; height:{{ $field['height'] ?? 10 }}%;
                border-radius:{{ $radiusMm }}mm;
                overflow: hidden;
                @if(!$hasGradient) background-color:{{ $solidBg }}; @endif
                @if(!empty($field['rotation'])) transform:rotate({{ (float) $field['rotation'] }}deg); transform-origin:center center; @endif
            ">
                @if($hasGradient)
                    <svg width="100%" height="100%" viewBox="0 0 100 100" preserveAspectRatio="none" style="display:block;width:100%;height:100%;position:absolute;top:0;left:0;border-radius:{{ $radiusMm }}mm;">
                        <defs>
                            <linearGradient id="{{ $gradId }}" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="{{ $field['gradient_from'] }}" />
                                <stop offset="100%" stop-color="{{ $field['gradient_to'] }}" />
                            </linearGradient>
                        </defs>
                        <rect width="100" height="100" rx="{{ $radiusMm > 0 ? 8 : 0 }}" ry="{{ $radiusMm > 0 ? 8 : 0 }}" fill="url(#{{ $gradId }})" />
                    </svg>
                @endif
            </div>
        @elseif($type === 'static_text')
            @if(!empty($field['text']))
                @php $wrap = (bool) ($field['wrap'] ?? false); @endphp
                <div class="card__field{{ $wrap ? ' card__field--wrap' : '' }}"
                     style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($field, ['font_size' => 9, 'font_family' => 'Arial']) }} @if(isset($field['height'])) height:{{ $field['height'] }}%; @endif @if(isset($field['line_height'])) line-height:{{ $field['line_height'] }}; @endif">
                    @if($wrap)<span>{{ $field['text'] }}</span>@else{{ $field['text'] }}@endif
                </div>
            @endif
        @elseif($type === 'divider')
            @php $orientation = $field['orientation'] ?? 'vertical'; @endphp
            <div class="card__divider card__divider--{{ $orientation }}" style="
                top:{{ $field['top'] ?? 0 }}%; left:{{ $field['left'] ?? 0 }}%;
                @if($orientation === 'vertical') height:{{ $field['height'] ?? 10 }}%;
                @else width:{{ $field['width'] ?? 10 }}%; @endif
                border-color:{{ $field['color'] ?? '#cbd5e1' }};
                @if(!empty($field['rotation'])) transform:rotate({{ (float) $field['rotation'] }}deg); transform-origin:center center; @endif
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
