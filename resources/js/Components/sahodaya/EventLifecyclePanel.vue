<template>
    <div class="card space-y-4 shadow-sm border border-slate-200/80">
        <!-- Progress Header & Bar -->
        <div>
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <h4 class="section-title !mb-0 text-sm">Event Progress</h4>
                <span class="text-xs font-bold text-slate-700 font-mono">
                    {{ completedCount }}/{{ totalCount }} <span class="text-slate-400 font-normal">({{ progressPercent }}%)</span>
                </span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full transition-all duration-300"
                     :style="{ width: `${progressPercent}%` }"></div>
            </div>
        </div>

        <!-- Suggested Status Banner (if applicable) -->
        <div v-if="suggestedStatus && suggestedStatus !== currentStatus"
             class="text-xs text-indigo-900 bg-indigo-50 border border-indigo-100 rounded-lg p-2.5 flex items-center justify-between gap-2">
            <span>Next phase: <strong>{{ suggestedStatus }}</strong></span>
            <button type="button" class="btn-primary text-xs !py-1 !px-2.5 shrink-0" :disabled="applying" @click="applySuggestedStatus">
                {{ applying ? 'Applying...' : 'Apply status' }}
            </button>
        </div>

        <!-- Step List Filter Control -->
        <div class="flex items-center justify-between text-xs pt-1">
            <span class="text-slate-400 uppercase tracking-wider font-bold text-[10px]">Checklist Steps</span>
            <button type="button"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                    @click="showAllSteps = !showAllSteps">
                {{ showAllSteps ? 'Show pending only' : `Show all (${totalCount})` }}
            </button>
        </div>

        <!-- Compact Checklist Items -->
        <ul class="divide-y divide-slate-100 text-xs">
            <li v-for="step in visibleSteps" :key="step.key"
                class="py-2.5 flex items-center justify-between gap-2 group transition">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                          :class="step.done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'">
                        {{ step.done ? '✓' : '○' }}
                    </span>
                    <span class="truncate font-medium" :class="step.done ? 'text-slate-500 line-through' : 'text-slate-800 font-semibold'">
                        {{ step.label }}
                        <span v-if="step.optional" class="ml-1 text-[9px] font-normal uppercase tracking-wide text-slate-400 no-underline">(Opt)</span>
                    </span>
                </div>
                <Link v-if="step.href || stepLink(step.key)"
                      :href="step.href || stepLink(step.key)"
                      class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline shrink-0">
                    Open →
                </Link>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    lifecycle: { type: Array, default: () => [] },
    suggestedStatus: { type: String, default: null },
    currentStatus: { type: String, default: null },
    eventType: { type: String, default: '' },
});

const showAllSteps = ref(false);
const isSports = computed(() => props.eventType === 'sports');
const applying = ref(false);

const completedCount = computed(() => props.lifecycle.filter((s) => s.done).length);
const totalCount = computed(() => props.lifecycle.length);
const progressPercent = computed(() => (totalCount.value ? Math.round((completedCount.value / totalCount.value) * 100) : 0));

const visibleSteps = computed(() => {
    if (showAllSteps.value) return props.lifecycle;
    const pending = props.lifecycle.filter((s) => !s.done);
    return pending.length > 0 ? pending : props.lifecycle;
});

function applySuggestedStatus() {
    const incomplete = props.lifecycle.filter((s) => !s.done && !s.optional);
    let message = `Change event status to "${props.suggestedStatus}"?`;
    if (incomplete.length) {
        const items = incomplete.map((s) => `• ${s.label}`).join('\n');
        message = `These checklist items aren't done yet:\n\n${items}\n\nChange status to "${props.suggestedStatus}" anyway?`;
    }
    if (!confirm(message)) return;

    applying.value = true;
    router.post(`${base.value}/quick-status`, { status: props.suggestedStatus }, {
        preserveScroll: true,
        onFinish: () => { applying.value = false; },
    });
}

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
