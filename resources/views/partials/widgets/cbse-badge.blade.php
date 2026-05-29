@php
    $affNo = $widgets['cbse_affiliation_number'] ?? null;
    $show  = $widgets['cbse_badge_show'] ?? true;
@endphp
@if($show && $affNo)
<div class="fixed bottom-6 left-6 z-50">
    <a href="https://cbseaff.nic.in/cbse_aff/schsearch.aspx" target="_blank" rel="noopener"
       class="flex items-center gap-2 bg-white border border-gray-200 rounded-full shadow-lg px-4 py-2 hover:shadow-xl transition-shadow"
       aria-label="CBSE Affiliation">
        <div class="w-8 h-8 rounded-full bg-blue-700 flex items-center justify-center shrink-0">
            <span class="text-white font-bold text-xs leading-none">CBSE</span>
        </div>
        <div class="text-xs leading-tight">
            <div class="font-semibold text-gray-800">Affiliated</div>
            <div class="text-gray-500">No. {{ $affNo }}</div>
        </div>
    </a>
</div>
@endif
