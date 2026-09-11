<template>
    <details v-if="logs?.length" class="form-section group">
        <summary class="form-section-head cursor-pointer list-none flex items-center justify-between gap-2">
            <div>
                <h3 class="form-section-title">Activity log</h3>
                <p class="form-section-hint">Recent actions on this page</p>
            </div>
            <span class="text-xs text-slate-400 group-open:rotate-180 transition">▼</span>
        </summary>
        <div class="form-section-body !pt-0">
            <ul class="divide-y divide-slate-100 text-sm">
                <li v-for="log in logs" :key="log.id" class="py-2.5 flex flex-wrap gap-x-3 gap-y-1">
                    <span class="text-slate-400 text-xs shrink-0" :title="deviceTitle(log)">{{ formatTime(log.created_at) }}</span>
                    <span class="text-slate-800 flex-1 min-w-0">
                        {{ log.description }}
                        <span v-if="log.participant" class="text-slate-500">— {{ log.participant }}</span>
                    </span>
                    <span v-if="log.user?.name" class="text-xs text-slate-500 shrink-0">
                        {{ log.user.name }}
                        <span v-if="log.actor_type" class="text-slate-400">({{ log.actor_type }})</span>
                    </span>
                    <span v-if="log.reason" class="basis-full text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5">
                        Reason: {{ log.reason }}
                    </span>
                </li>
            </ul>
        </div>
    </details>
</template>

<script setup>
defineProps({
    logs: { type: Array, default: () => [] },
});

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Hover-only (not printed inline) so the list stays scannable — IP/device is
// forensic detail admins need occasionally, not something to show by default.
function deviceTitle(log) {
    const parts = [];
    if (log.ip_address) parts.push(`IP: ${log.ip_address}`);
    if (log.user_agent) parts.push(`Device: ${log.user_agent}`);
    return parts.join('\n') || undefined;
}
</script>

<style scoped>
details > summary::-webkit-details-marker {
    display: none;
}
</style>
