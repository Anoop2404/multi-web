<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Food Coupons</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px}
.coupon{border:2px dashed #333;padding:12px;margin-bottom:16px;page-break-inside:avoid}
.code{font-size:18px;font-weight:bold;letter-spacing:2px}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => ($sahodaya->name ?? 'Sahodaya') . ' — ' . $event->title, 'logoSrc' => $logoSrc ?? null])
<p style="text-align:center;color:#666">Food coupons</p>
@foreach($coupons as $c)
<div class="coupon">
    <p class="code">{{ $c->coupon_code }}</p>
    <p><strong>{{ $c->school?->name }}</strong></p>
    <p>{{ ucfirst($c->meal_type) }} · {{ $c->valid_date?->format('d M Y') }} · {{ $c->head_count }} head(s)</p>
    @if($c->notes)<p style="color:#666">{{ $c->notes }}</p>@endif
</div>
@endforeach
</body></html>
