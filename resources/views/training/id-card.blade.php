<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Training ID — {{ $teacherName }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; }
        .card {
            width: 96mm;
            height: 72mm;
            border: 1px solid #cbd5e1;
            overflow: hidden;
            background: #fff;
            position: relative;
        }
        .card__head {
            display: table;
            width: 100%;
            padding: 2.2mm 2.5mm;
            color: #fff;
            background: #0f3d7a;
        }
        .card__brand { display: table-cell; vertical-align: middle; width: 70%; }
        .card__logo {
            width: 8mm;
            height: 8mm;
            object-fit: contain;
            vertical-align: middle;
            margin-right: 1.5mm;
            background: #fff;
            border-radius: 1mm;
        }
        .card__org {
            display: inline-block;
            vertical-align: middle;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            max-width: 52mm;
            line-height: 1.2;
        }
        .card__role {
            display: table-cell;
            font-size: 7px;
            font-weight: bold;
            text-align: right;
            vertical-align: middle;
            letter-spacing: 0.08em;
        }
        .card__body { display: table; width: 100%; padding: 3mm 2.5mm 2mm; }
        .card__avatar {
            display: table-cell;
            width: 20mm;
            vertical-align: top;
        }
        .card__photo {
            width: 18mm;
            height: 18mm;
            border-radius: 50%;
            object-fit: cover;
            border: 0.4mm solid #e2e8f0;
        }
        .card__initials {
            width: 18mm;
            height: 18mm;
            border-radius: 50%;
            background: #e2e8f0;
            color: #0f3d7a;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            line-height: 18mm;
        }
        .card__info { display: table-cell; vertical-align: top; padding-left: 2.5mm; }
        .card__name { font-size: 11px; font-weight: bold; line-height: 1.2; margin-bottom: 1mm; }
        .card__sub { font-size: 7.5px; color: #475569; line-height: 1.3; }
        .card__program {
            margin-top: 1.5mm;
            font-size: 7px;
            color: #0f3d7a;
            font-weight: bold;
            line-height: 1.25;
        }
        .card__ids {
            display: table;
            width: 100%;
            border-top: 0.4mm solid #e2e8f0;
            padding: 1.5mm 2.5mm;
            background: #f8fafc;
            position: absolute;
            bottom: 0;
            left: 0;
        }
        .card__id-block { display: table-cell; width: 55%; vertical-align: top; }
        .card__id-label { font-size: 5.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
        .card__id-value { font-size: 10px; font-weight: bold; font-family: DejaVu Sans Mono, monospace; color: #0f3d7a; }
        .card__status {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #047857;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card__head">
        <div class="card__brand">
            @if(!empty($logoSrc))
                <img src="{{ $logoSrc }}" alt="" class="card__logo">
            @endif
            <span class="card__org">{{ $orgName }}</span>
        </div>
        <div class="card__role">TRAINEE</div>
    </div>

    <div class="card__body">
        <div class="card__avatar">
            @if(!empty($photoSrc))
                <img src="{{ $photoSrc }}" alt="" class="card__photo">
            @else
                <div class="card__initials">{{ $initials }}</div>
            @endif
        </div>
        <div class="card__info">
            <p class="card__name">{{ $teacherName }}</p>
            <p class="card__sub">{{ $schoolName }}</p>
            <p class="card__program">{{ $programTitle }}</p>
        </div>
    </div>

    <div class="card__ids">
        <div class="card__id-block">
            <div class="card__id-label">Registration ID</div>
            <div class="card__id-value">TRN-{{ $regId }}</div>
        </div>
        <div class="card__status">{{ $status }}</div>
    </div>
</div>
</body>
</html>
