<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 mb-4 overflow-x-auto">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Event workflow</p>
        <ol class="flex flex-wrap gap-1 min-w-max">
            <li v-for="step in steps" :key="step.key" class="flex items-center">
                <component
                    :is="step.href ? 'a' : 'span'"
                    :href="step.href || undefined"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition"
                    :class="step.key === currentStep
                        ? 'bg-[#0f3d7a] text-white'
                        : step.href
                            ? 'bg-white border border-slate-200 text-slate-700 hover:border-[#0f3d7a]/40'
                            : 'bg-slate-100 text-slate-400'"
                >
                    <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px]"
                          :class="step.key === currentStep ? 'bg-white/20' : 'bg-slate-200 text-slate-600'">
                        {{ step.num }}
                    </span>
                    {{ step.label }}
                </component>
                <span v-if="step.key !== steps[steps.length - 1].key" class="text-slate-300 mx-1">→</span>
            </li>
        </ol>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    eventType: { type: String, default: 'sports' },
    programSlug: { type: String, default: '' },
    currentStep: { type: String, default: 'registration' },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}`);
const eventBase = computed(() => `${base.value}/events/${props.eventId}`);

const steps = computed(() => {
    if (props.eventType === 'sports') {
        return [
            {
                num: 1,
                key: 'setup',
                label: 'Setup',
                href: `${eventBase.value}/setup`,
            },
            {
                num: 2,
                key: 'competition',
                label: 'Item heads',
                href: `${eventBase.value}/competition`,
            },
            {
                num: 3,
                key: 'registration',
                label: 'Open registration',
                href: `${eventBase.value}/registrations`,
            },
            {
                num: 4,
                key: 'operations',
                label: 'Ongoing / marks',
                href: `${eventBase.value}/marks`,
            },
            {
                num: 5,
                key: 'reports',
                label: 'Publish results',
                href: `${eventBase.value}/results`,
            },
        ];
    }

    return [
        {
            num: 1,
            key: 'setup',
            label: 'Items & settings',
            href: `${eventBase.value}/settings`,
        },
        {
            num: 2,
            key: 'registration',
            label: 'Registrations',
            href: `${eventBase.value}/registrations`,
        },
        {
            num: 3,
            key: 'operations',
            label: 'Marks',
            href: `${eventBase.value}/marks`,
        },
        {
            num: 4,
            key: 'results',
            label: 'Results',
            href: `${eventBase.value}/results`,
        },
    ];
});
</script>
