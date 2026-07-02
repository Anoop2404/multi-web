@extends('layouts.public')

@section('content')
<section class="py-12 px-4 bg-slate-950 text-white min-h-screen" id="fest-live-root" data-live-url="{{ route('tenant.fest.live.data', $event->id) }}">
    <div class="max-w-3xl mx-auto">
        <p class="text-amber-400 text-xs uppercase tracking-widest text-center">Live</p>
        <h1 class="text-3xl font-bold text-center mt-1">{{ $event->title }}</h1>
        <p id="live-refresh-badge" class="text-center text-[10px] text-white/40 mt-2">Auto-refreshing every 30s</p>
        <div id="now-performing" class="mt-6 p-4 bg-white/10 rounded-xl text-center text-sm @if(!$nowPerforming) hidden @endif">
            @if($nowPerforming)
            Now: <strong>{{ $nowPerforming['item_title'] ?? '—' }}</strong>
            @if(($nowPerforming['reference'] ?? '—') !== '—')
            — <span class="font-mono">#{{ $nowPerforming['reference'] }}</span>
            @endif
            @if(!empty($nowPerforming['show_name']) && !empty($nowPerforming['name']))
            {{ $nowPerforming['name'] }}
            @endif
            @endif
        </div>
        <h2 class="font-semibold mt-10 mb-3">School Standings</h2>
        <ol id="school-scoreboard" class="space-y-2">
            @forelse($scoreboard as $row)
            <li class="flex justify-between bg-white/5 border border-white/10 rounded-lg px-4 py-3">
                <span><span class="text-amber-400 font-bold mr-2">#{{ $row['rank'] }}</span>{{ $row['school_name'] }}</span>
                <span class="font-mono">{{ $row['total_points'] }}</span>
            </li>
            @empty
            <li class="text-white/40 text-center py-6">No scores yet</li>
            @endforelse
        </ol>
        @if(count($houseScoreboard))
        <h2 class="font-semibold mt-10 mb-3">House Standings</h2>
        <ol id="house-scoreboard" class="space-y-2">
            @foreach($houseScoreboard as $row)
            <li class="flex justify-between bg-white/5 border border-white/10 rounded-lg px-4 py-3">
                <span><span class="inline-block w-3 h-3 rounded-full mr-2" style="background:{{ $row['color'] ?? '#fbbf24' }}"></span>#{{ $row['rank'] }} {{ $row['house_name'] }}</span>
                <span class="font-mono">{{ $row['total_points'] }}</span>
            </li>
            @endforeach
        </ol>
        @endif
        @if(count($athleticRecords ?? []))
        <h2 class="font-semibold mt-10 mb-3">Athletic Records</h2>
        <ol id="athletic-records" class="space-y-2 text-sm">
            @foreach($athleticRecords as $r)
            <li class="bg-white/5 border border-white/10 rounded-lg px-4 py-3">
                <span class="font-medium">{{ $r['item'] }}</span>
                <span class="text-white/60"> · {{ $r['class_group'] }} {{ $r['gender'] }}</span>
                <span class="float-right font-mono text-amber-300">{{ $r['value'] }} {{ $r['unit'] }}</span>
            </li>
            @endforeach
        </ol>
        @endif
        @if(count($recentBreaks ?? []))
        <h2 class="font-semibold mt-10 mb-3">Recent Record Breaks</h2>
        <ul id="recent-breaks" class="space-y-2 text-sm">
            @foreach($recentBreaks as $b)
            <li class="bg-amber-500/10 border border-amber-500/30 rounded-lg px-4 py-3">
                <strong>{{ $b['name'] ?? 'Participant' }}</strong> — {{ $b['item'] }}
                <span class="block text-amber-300 font-mono mt-1">{{ $b['new_value'] }} {{ $b['unit'] }} · {{ $b['prize_label'] }}</span>
            </li>
            @endforeach
        </ul>
        @endif
        <p class="mt-8 text-center space-x-4">
            <a href="{{ route('tenant.fest.records', $event->id) }}" class="text-amber-400 text-sm">All records →</a>
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-amber-400 text-sm">← Festival hub</a>
        </p>
    </div>
</section>
<script>
(function () {
    const root = document.getElementById('fest-live-root');
    if (!root) return;
    const url = root.dataset.liveUrl;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function renderSchool(rows) {
        const el = document.getElementById('school-scoreboard');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = '<li class="text-white/40 text-center py-6">No scores yet</li>';
            return;
        }
        el.innerHTML = rows.map(r => `<li class="flex justify-between bg-white/5 border border-white/10 rounded-lg px-4 py-3">
            <span><span class="text-amber-400 font-bold mr-2">#${esc(r.rank)}</span>${esc(r.school_name)}</span>
            <span class="font-mono">${esc(r.total_points)}</span></li>`).join('');
    }

    function renderHouse(rows) {
        const el = document.getElementById('house-scoreboard');
        if (!el) return;
        el.innerHTML = rows.map(r => `<li class="flex justify-between bg-white/5 border border-white/10 rounded-lg px-4 py-3">
            <span><span class="inline-block w-3 h-3 rounded-full mr-2" style="background:${esc(r.color || '#fbbf24')}"></span>#${esc(r.rank)} ${esc(r.house_name)}</span>
            <span class="font-mono">${esc(r.total_points)}</span></li>`).join('');
    }

    function renderNow(p) {
        const el = document.getElementById('now-performing');
        if (!el) return;
        if (!p) { el.classList.add('hidden'); return; }
        el.classList.remove('hidden');
        let html = `Now: <strong>${esc(p.item_title || '—')}</strong>`;
        if ((p.reference || '—') !== '—') html += ` — <span class="font-mono">#${esc(p.reference)}</span>`;
        if (p.show_name && p.name) html += ` ${esc(p.name)}`;
        el.innerHTML = html;
    }

    async function refresh() {
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderSchool(data.scoreboard || []);
            renderHouse(data.houseScoreboard || []);
            renderNow(data.nowPerforming);
            const badge = document.getElementById('live-refresh-badge');
            if (badge && data.refreshedAt) badge.textContent = 'Updated ' + new Date(data.refreshedAt).toLocaleTimeString();
        } catch (e) { /* silent */ }
    }

    setInterval(refresh, 30000);
})();
</script>
@endsection
