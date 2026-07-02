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
                    <span class="text-slate-400 text-xs shrink-0">{{ formatTime(log.created_at) }}</span>
                    <span class="text-slate-800 flex-1 min-w-0">{{ log.description }}</span>
                    <span v-if="log.user?.name" class="text-xs text-slate-500">{{ log.user.name }}</span>
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
</script>

<style scoped>
details > summary::-webkit-details-marker {
    display: none;
}
</style>
