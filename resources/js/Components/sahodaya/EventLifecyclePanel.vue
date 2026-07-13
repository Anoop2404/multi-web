<template>
    <div class="card space-y-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h4 class="section-title">Event progress</h4>
                <p class="section-desc text-xs mt-0.5">
                    {{ isSports
                        ? 'Sports phases: Setup → Open registration (schools see event) → Ongoing → Publish results → Complete.'
                        : 'Lifecycle checklist — complete each step before fest day.' }}
                </p>
            </div>
            <Link :href="`${base}/settings/lifecycle`" class="text-xs link-brand shrink-0">Full checklist →</Link>
        </div>
        <p v-if="suggestedStatus" class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
            Suggested status: <strong>{{ suggestedStatus }}</strong>
        </p>
        <ul class="space-y-2">
                <li v-for="step in lifecycle" :key="step.key"
                class="flex items-start gap-2.5 text-sm border rounded-lg px-3 py-2.5"
                :class="step.done
                    ? 'bg-emerald-50/80 border-emerald-200'
                    : step.optional
                        ? 'bg-slate-50/60 border-slate-100'
                        : 'bg-slate-50 border-slate-200'">
                <span class="text-base leading-none">{{ step.done ? '✓' : '○' }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-800">
                        {{ step.label }}
                        <span v-if="step.optional" class="ml-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">Optional</span>
                    </p>
                    <p v-if="step.hint" class="text-xs text-slate-500 mt-0.5">{{ step.hint }}</p>
                    <p v-if="step.detail" class="text-xs text-slate-400 mt-0.5">{{ step.detail }}</p>
                    <Link v-if="step.href || stepLink(step.key)" :href="step.href || stepLink(step.key)"
                          class="inline-block mt-1.5 text-xs font-semibold link-brand">
                        Open →
                    </Link>
                </div>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    lifecycle: { type: Array, default: () => [] },
    suggestedStatus: { type: String, default: null },
    eventType: { type: String, default: '' },
});

const isSports = computed(() => props.eventType === 'sports');
const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const eventQuery = computed(() => `?event_id=${props.eventId}`);

const links = computed(() => ({
    event: `${base.value}?overview=1`,
    heads: `${base.value}/competition`,
    head_windows: `${base.value}/competition`,
    items: `${base.value}/items`,
    item_fees: `${base.value}/competition`,
    fees: `${base.value}/settings/fees`,
    rank_points: `${base.value}/settings/points`,
    registration: `${base.value}/settings/registration`,
    numbering: `${base.value}/settings/numbering`,
    registrations: `${base.value}/registrations`,
    school_fees: `${base.value}/fees`,
    state_remittance: `/sahodaya-admin/${props.sahodayaId}/state-remittances${eventQuery.value}`,
    schedule: `${base.value}/schedule`,
    schedule_published: `${base.value}/schedule`,
    ongoing: base.value,
    marks: `${base.value}/marks`,
    published: `${base.value}/results`,
}));

function stepLink(key) {
    return links.value[key] ?? null;
}
</script>
