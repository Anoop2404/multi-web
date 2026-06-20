<template>
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="bg-white rounded-2xl border border-[#dbeafe] shadow-sm p-6 sm:p-8 text-center">
            <div class="text-5xl mb-4">{{ meta.icon }}</div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#b45309] mb-2">Coming soon</p>
            <h2 class="text-xl font-bold text-[#041525]">{{ pageTitle }}</h2>
            <p class="text-sm text-gray-500 mt-2 leading-relaxed">{{ hint }}</p>
        </div>

        <div class="bg-[#f8fafc] rounded-xl border border-dashed border-[#cbd5e1] p-5 text-center">
            <p class="text-sm font-semibold text-[#64748b]">{{ statusLabel }}</p>
            <p class="text-xs text-[#94a3b8] mt-1">
                {{ schoolPortal ? 'Placeholder until Sahodaya enables this event.' : 'Placeholder until this feature is enabled.' }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    meta:         { type: Object, required: true },
    view:         { type: String, required: true },
    schoolPortal: { type: Boolean, default: false },
});

const pageTitle = computed(() =>
    props.view === 'results' ? `${props.meta.label} Results` : `${props.meta.label} Registration`,
);

const statusLabel = computed(() =>
    props.view === 'results' ? 'No results published yet' : 'Registration not started',
);

const hint = computed(() => {
    if (props.view === 'results') {
        return props.meta.results_hint;
    }

    if (props.schoolPortal) {
        return `${props.meta.registration_hint} You will be notified when registration opens.`;
    }

    return props.meta.registration_hint;
});
</script>
