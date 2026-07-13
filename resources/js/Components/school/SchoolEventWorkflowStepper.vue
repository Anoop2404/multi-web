<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 mb-4 overflow-x-auto">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Event workflow</p>
        <ol class="flex flex-wrap gap-1 min-w-max">
            <li v-for="step in steps" :key="step.key" class="flex items-center">
                <Link v-if="step.href"
                      :href="step.href"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition"
                      :class="step.key === currentStep
                          ? 'bg-[#0f3d7a] text-white'
                          : 'bg-white border border-slate-200 text-slate-700 hover:border-[#0f3d7a]/40'">
                    <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px]"
                          :class="step.key === currentStep ? 'bg-white/20' : 'bg-slate-200 text-slate-600'">
                        {{ step.num }}
                    </span>
                    {{ step.label }}
                </Link>
                <span v-else
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-400">
                    <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] bg-slate-200 text-slate-600">{{ step.num }}</span>
                    {{ step.label }}
                </span>
                <span v-if="step.key !== steps[steps.length - 1].key" class="text-slate-300 mx-1">→</span>
            </li>
        </ol>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { schoolEventBase } from '@/support/eventHeadNav.js';

const props = defineProps({
    schoolId: { type: [String, Number], required: true },
    programPrefix: { type: String, required: true },
    eventId: { type: [String, Number], required: true },
    isSports: { type: Boolean, default: false },
    currentStep: { type: String, default: 'overview' },
});

const eventBase = computed(() => schoolEventBase(props.schoolId, props.programPrefix, props.eventId));

const steps = computed(() => {
    const list = [
        { num: 1, key: 'overview', label: 'Overview', href: `${eventBase.value}/overview` },
        { num: 2, key: 'registration', label: props.isSports ? 'Register students' : 'Register', href: `${eventBase.value}/registration` },
    ];

    if (props.isSports) {
        list.push({ num: 3, key: 'items', label: 'By Event Head', href: `${eventBase.value}/items` });
    }

    list.push({
        num: props.isSports ? 4 : 3,
        key: 'reports',
        label: 'Reports & ID cards',
        href: `/school-admin/${props.schoolId}/${props.programPrefix}/reports/${props.eventId}`,
    });

    return list;
});
</script>
