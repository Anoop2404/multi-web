<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Admit Cards</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}
.card{border:2px solid #1e3a8a;padding:12px;margin-bottom:16px;page-break-inside:avoid}
h3{margin:0 0 6px;color:#1e3a8a;font-size:14px}
.meta{font-size:10px;color:#475569}
</style></head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Participant Admit Cards</h2>
@foreach($participants as $p)
<div class="card">
<h3>{{ $p->student?->name ?? $p->teacher?->name }}</h3>
<p class="meta">
<strong>Item:</strong> {{ $p->registration?->item?->title }} ·
<strong>School:</strong> {{ $p->registration?->school?->name }} ·
<strong>Chest:</strong> {{ $p->chest_no ?? 'TBA' }} ·
<strong>Class:</strong> {{ strtoupper($p->registration?->item?->class_group ?? '') }}
</p>
@if($event->venue)<p class="meta"><strong>Venue:</strong> {{ $event->venue }}</p>@endif
</div>
@endforeach
</body></html>
