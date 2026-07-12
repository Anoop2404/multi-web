{{--
    Shared base rules for printable ID card templates.

    Included by:
      - resources/views/training/id-card.blade.php
      - resources/views/fest/id-cards/sheet.blade.php

    Only rules that are byte-identical across those templates live here, so this file
    can be edited without needing to check every template's rendered output stays the
    same. Anything that differs even slightly (padding, font sizes, per-template
    layout) stays local to each template's own <style> block.
--}}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; }
.card__role { display: table-cell; font-size: 7px; font-weight: bold; text-align: right; vertical-align: middle; letter-spacing: 0.08em; }
.card__id-label { font-size: 5.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
.card__initials, .card__avatar-inner {
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
