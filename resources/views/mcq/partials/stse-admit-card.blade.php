@php
    $die = \App\Support\Mcq\McqHallTicketDesign::printGeometry($design);
    $artwork = \App\Support\Mcq\McqHallTicketDesign::artworkBox($design);
@endphp
<div style="position:relative;width:{{ $die['width'] }}mm;height:{{ $die['height'] }}mm;break-inside:avoid;page-break-inside:avoid;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#111;background:white;">
    <div style="position:absolute;left:{{ $artwork['x'] }}mm;top:{{ $artwork['y'] }}mm;width:{{ $artwork['width'] }}mm;height:{{ $artwork['height'] }}mm;">
    <img src="{{ ($design['layout'] ?? '') === 'custom_background' ? \App\Support\TenantStorage::logoUrl($tenant ?? null, $design['background_path'] ?? null) : asset('images/mcq/stse-preliminary-background.png') }}" alt="STSE Preliminary admit card" style="position:absolute;inset:0;width:100%;height:100%;">
    @foreach([
        'registration' => $sample['hall_ticket_no'] ?? '',
        'name' => $sample['participant_name'] ?? $sample['student_name'] ?? '',
        'class' => $sample['class_name'] ?? '',
        'date' => $sample['exam_date'] ?? '',
        'centre' => $sample['venue'] ?? '',
    ] as $key => $value)
        @php $position = $design['field_positions'][$key] ?? \App\Support\Mcq\McqHallTicketDesign::defaults()['field_positions'][$key]; @endphp
        <span style="position:absolute;top:{{ $position['y'] }}%;left:{{ $position['x'] }}%;width:{{ $position['width'] ?? 50 }}%;line-height:1.25;font-size:{{ $position['size'] * $artwork['height'] / 121.384615 }}px;font-family:{{ $position['font_family'] ?? 'Arial' }};font-weight:{{ $position['font_weight'] ?? 'normal' }};font-style:{{ $position['font_style'] ?? 'normal' }};text-align:{{ $position['text_align'] ?? 'left' }};overflow-wrap:anywhere;">{{ $value }}</span>
    @endforeach
    @if(($design['show_student_photo'] ?? false) && !empty($sample['photo_src']))
        @php $photoPosition = $design['field_positions']['student_photo']; @endphp
        <img src="{{ $sample['photo_src'] }}" alt="Student photo" style="position:absolute;left:{{ $photoPosition['x'] }}%;top:{{ $photoPosition['y'] }}%;width:{{ $photoPosition['width'] }}%;height:{{ $photoPosition['height'] }}%;object-fit:contain;">
    @endif
    @foreach($design['custom_fields'] ?? [] as $field)
        <span style="position:absolute;left:{{ $field['x'] }}%;top:{{ $field['y'] }}%;width:{{ $field['width'] }}%;font-size:{{ $field['size'] * $artwork['height'] / 121.384615 }}px;font-family:{{ $field['font_family'] ?? 'Arial' }};font-weight:{{ $field['font_weight'] ?? 'normal' }};font-style:{{ $field['font_style'] ?? 'normal' }};text-align:{{ $field['text_align'] ?? 'left' }};line-height:1.25;overflow-wrap:anywhere;">{{ \App\Support\Mcq\McqHallTicketDesign::customFieldValue($field, $sample) }}</span>
    @endforeach
    @if(!empty($sample['principal_signature_src']) && ($design['show_signature'] ?? true))
        @php $signaturePosition = $design['field_positions']['principal_signature']; @endphp
        <img src="{{ $sample['principal_signature_src'] }}" alt="Principal signature" style="position:absolute;left:{{ $signaturePosition['x'] }}%;top:{{ $signaturePosition['y'] }}%;width:{{ $signaturePosition['width'] }}%;height:6%;object-fit:contain;">
    @endif
    </div>
</div>
