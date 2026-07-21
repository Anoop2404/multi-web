<template>
    <nav class="flex flex-wrap gap-1.5 bg-slate-100/80 p-1.5 rounded-xl border border-slate-200/80 mb-5 overflow-x-auto shadow-inner"
         aria-label="School event registration steps">
        <button v-for="step in steps" :key="step.key"
                type="button"
                :class="currentStep === step.key
                    ? 'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold bg-slate-900 text-white shadow-sm transition whitespace-nowrap'
                    : 'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-white/70 transition whitespace-nowrap'"
                @click="onStepClick(step)">
            <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                  :class="currentStep === step.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                {{ step.num }}
            </span>
            <span>{{ step.label }}</span>
        </button>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { schoolEventBase } from '@/support/eventHeadNav.js';

const props = defineProps({
    schoolId: { type: [String, Number], required: true },
    programPrefix: { type: String, required: true },
    eventId: { type: [String, Number], required: true },
    isSports: { type: Boolean, default: false },
    currentStep: { type: String, default: 'event-reg' },
});

const emit = defineEmits(['select-step']);

const eventBase = computed(() => schoolEventBase(props.schoolId, props.programPrefix, props.eventId));

const steps = computed(() => [
    { num: 1, key: 'event-reg', tab: 'athletes', label: '1. Event Registration', href: `${eventBase.value}/registration?tab=event-reg` },
    { num: 2, key: 'item-reg', tab: 'items', label: '2. Item Registration', href: `${eventBase.value}/registration?tab=item-reg` },
    { num: 3, key: 'payment', tab: 'payment', label: '3. Payment & Fees', href: `${eventBase.value}/registration?tab=payment` },
]);

function onStepClick(step) {
    emit('select-step', step);
}
</script>
