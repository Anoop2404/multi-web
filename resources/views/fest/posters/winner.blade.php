<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080" viewBox="0 0 1080 1080">
    <defs>
        <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#0f172a"/>
            <stop offset="100%" style="stop-color:#1e293b"/>
        </linearGradient>
    </defs>
    <rect width="1080" height="1080" fill="url(#bg)"/>
    <rect x="48" y="48" width="984" height="984" rx="32" fill="none" stroke="{{ $accent }}" stroke-width="6"/>
    <text x="540" y="120" text-anchor="middle" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="28" letter-spacing="4">{{ strtoupper($tenantName) }}</text>
    <text x="540" y="180" text-anchor="middle" fill="#f8fafc" font-family="system-ui, sans-serif" font-size="42" font-weight="700">{{ $eventTypeLabel }}</text>
    <text x="540" y="230" text-anchor="middle" fill="#cbd5e1" font-family="system-ui, sans-serif" font-size="26">{{ $eventTitle }}</text>
    <circle cx="540" cy="420" r="110" fill="{{ $accent }}" opacity="0.15"/>
    <text x="540" y="440" text-anchor="middle" fill="{{ $accent }}" font-family="system-ui, sans-serif" font-size="56" font-weight="800">{{ $positionLabel }}</text>
    <text x="540" y="580" text-anchor="middle" fill="#ffffff" font-family="system-ui, sans-serif" font-size="52" font-weight="700">{{ $winnerName }}</text>
    @if($schoolName)
    <text x="540" y="650" text-anchor="middle" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="30">{{ $schoolName }}</text>
    @endif
    <text x="540" y="760" text-anchor="middle" fill="#fbbf24" font-family="system-ui, sans-serif" font-size="34" font-weight="600">{{ $itemTitle }}</text>
    @if($measurement)
    <text x="540" y="820" text-anchor="middle" fill="#e2e8f0" font-family="system-ui, sans-serif" font-size="28">{{ $measurement }}</text>
    @elseif($grade)
    <text x="540" y="820" text-anchor="middle" fill="#e2e8f0" font-family="system-ui, sans-serif" font-size="28">Grade: {{ $grade }}@if($score) · {{ $score }}@endif</text>
    @endif
    <text x="540" y="980" text-anchor="middle" fill="#64748b" font-family="system-ui, sans-serif" font-size="22">Sahodaya Connect</text>
</svg>
