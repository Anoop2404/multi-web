<template>
    <div class="space-y-4">
        <div>
            <h3 class="section-title">{{ title }}</h3>
            <p class="section-desc">{{ description }}</p>
        </div>

        <!-- Status Summary Banner -->
        <div
            class="rounded-xl border px-3.5 py-2.5 text-xs flex flex-wrap items-center justify-between gap-2"
            :class="local.enabled ? 'border-primary-300 bg-primary-50/70 text-primary-950' : 'border-slate-200 bg-slate-50 text-slate-700'"
        >
            <div class="flex items-center gap-2">
                <span class="text-base">{{ local.enabled ? '⚡' : '🛡️' }}</span>
                <div>
                    <span class="font-bold block">
                        {{ local.enabled ? 'Custom exam rate limits active' : 'Inheriting platform / Sahodaya defaults' }}
                    </span>
                    <span class="text-[11px] opacity-80">
                        {{
                            local.enabled
                                ? `Cap of ${local.max_concurrent_per_school || 'unlimited'} concurrent students per school writing this exam.`
                                : effectiveSummary
                        }}
                    </span>
                </div>
            </div>

            <span
                class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                :class="local.enabled ? 'bg-primary-600 text-white' : 'bg-slate-200 text-slate-700'"
            >
                {{ local.enabled ? 'Exam Override' : 'Inherited' }}
            </span>
        </div>

        <div class="space-y-3">
            <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer transition"
                   :class="local.enabled ? 'border-primary-300 bg-primary-50/30 ring-1 ring-primary-300' : 'border-slate-200 bg-white hover:border-slate-300'">
                <input
                    v-model="local.enabled"
                    type="checkbox"
                    class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                    @change="onToggle"
                />
                <div>
                    <span class="text-xs font-bold text-slate-800 block">Override concurrency limits for this exam</span>
                    <span class="text-[11px] text-slate-500 block mt-0.5">
                        Define a custom cap for how many students can write this specific exam at the exact same time from one school.
                    </span>
                </div>
            </label>

            <div v-if="local.enabled" class="pl-4 sm:pl-7 pt-1 space-y-3">
                <div class="max-w-md space-y-1">
                    <label class="block text-xs font-bold text-slate-700">
                        Max concurrent students per school
                    </label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model.number="local.max_concurrent_per_school"
                            type="number"
                            min="1"
                            step="1"
                            placeholder="e.g. 50"
                            class="field min-h-10 text-sm font-bold max-w-[12rem]"
                            @input="emitUpdate"
                        />
                        <span class="text-xs text-slate-500">students writing simultaneously</span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-normal pt-0.5">
                        If more students from the same school click "Start Exam" while this cap is reached, they will see a friendly waiting message asking them to retry once earlier students complete.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ enabled: false, max_concurrent_per_school: null }),
    },
    effectiveLimits: {
        type: Object,
        default: () => ({ enabled: false, max_concurrent_per_school: null, max_concurrent_per_sahodaya: null }),
    },
    title: {
        type: String,
        default: 'Concurrency & Capacity Limits',
    },
    description: {
        type: String,
        default: 'Set rate-limiting controls to prevent local school network saturation and distribute load during mass online exam sittings.',
    },
});

const emit = defineEmits(['update:modelValue']);

const local = reactive({
    enabled: Boolean(props.modelValue?.enabled),
    max_concurrent_per_school: props.modelValue?.max_concurrent_per_school ?? null,
});

const effectiveSummary = computed(() => {
    if (!props.effectiveLimits?.enabled) {
        return 'Concurrency controls are currently disabled at cluster/platform level.';
    }
    const schoolCap = props.effectiveLimits.max_concurrent_per_school;
    const clusterCap = props.effectiveLimits.max_concurrent_per_sahodaya;
    const parts = [];
    if (schoolCap) parts.push(`${schoolCap}/school`);
    if (clusterCap) parts.push(`${clusterCap}/cluster`);
    return `Effective active defaults: ${parts.join(', ') || 'No numerical cap'}.`;
});

function emitUpdate() {
    emit('update:modelValue', {
        enabled: local.enabled,
        max_concurrent_per_school: local.enabled && local.max_concurrent_per_school > 0 ? Number(local.max_concurrent_per_school) : null,
    });
}

function onToggle() {
    if (local.enabled && !local.max_concurrent_per_school) {
        local.max_concurrent_per_school = props.effectiveLimits?.max_concurrent_per_school || 50;
    }
    emitUpdate();
}

watch(
    () => props.modelValue,
    (val) => {
        local.enabled = Boolean(val?.enabled);
        local.max_concurrent_per_school = val?.max_concurrent_per_school ?? null;
    },
    { deep: true },
);
</script>
