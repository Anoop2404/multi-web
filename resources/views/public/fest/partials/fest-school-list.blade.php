<div class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden">
    <div class="grid grid-cols-[3.5rem_1fr] gap-2 px-5 py-3 bg-white/5 border-b border-slate-800 text-xs font-extrabold uppercase tracking-wider text-slate-400">
        <span>#</span>
        <span>School</span>
    </div>
    <div class="divide-y divide-slate-800/80">
        @forelse($schools as $seq => $name)
        <div class="grid grid-cols-[3.5rem_1fr] gap-2 items-center px-5 py-3">
            <span class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center text-sm font-extrabold">{{ $seq + 1 }}</span>
            <span class="font-bold text-white text-base truncate">{{ $name }}</span>
        </div>
        @empty
        <div class="text-slate-400 text-center py-12">No schools registered yet.</div>
        @endforelse
    </div>
</div>
