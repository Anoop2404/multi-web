<template>
    <div class="flex items-center gap-2.5 min-w-0">
        <div class="h-9 w-9 shrink-0 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
            <img v-if="photoUrl"
                 :src="photoUrl"
                 :alt="name"
                 class="h-full w-full object-cover"
                 loading="lazy">
            <span v-else class="text-xs font-bold text-slate-400">{{ initials }}</span>
        </div>
        <div class="min-w-0">
            <p class="font-medium text-slate-900 truncate">{{ name || '—' }}</p>
            <p v-if="regNo || classLabel" class="text-xs text-slate-500 truncate">
                <span v-if="regNo">{{ regNo }}</span>
                <span v-if="regNo && classLabel"> · </span>
                <span v-if="classLabel">{{ classLabel }}</span>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    regNo: { type: String, default: '' },
    classLabel: { type: String, default: '' },
    photoUrl: { type: String, default: null },
});

const initials = computed(() => {
    const parts = (props.name ?? '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('');
});
</script>
