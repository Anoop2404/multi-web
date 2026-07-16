<template>
    <div class="mb-6 space-y-4">
        <nav class="reports-tabs" aria-label="Reports navigation">

            <Link :href="`${base}/reports${isSports ? '?all=1' : ''}`"
                  class="reports-tab"
                  :class="{ 'reports-tab--active': active === 'hub' }">
                <span aria-hidden="true">📊</span> {{ isSports ? 'All types' : 'Overview' }}
            </Link>
            <Link v-for="phase in phases" :key="phase.key"
                  :href="`${base}/reports/downloads/${phase.key}`"
                  class="reports-tab"
                  :class="{
                      'reports-tab--active': active === phase.key,
                      'reports-tab--locked': !isPhaseAllowed(phase.key),
                  }"
                  @click="!isPhaseAllowed(phase.key) && $event.preventDefault()">
                <span aria-hidden="true">{{ phase.icon }}</span>
                {{ phase.shortLabel }}
            </Link>
        </nav>

        <FestEventMetaBar v-if="eventMeta && active !== 'hub'" :meta="eventMeta" compact />

        <Link v-if="active !== 'hub'"
              :href="`${base}/reports`"
              class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-indigo-600">
            ← All report types
        </Link>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FestEventMetaBar from '@/Components/reports/FestEventMetaBar.vue';
import { REPORT_PHASES } from '@/support/festReportCatalog.js';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, default: 'hub' },
});

const page = usePage();
const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const eventMeta = computed(() => page.props.eventMeta ?? null);
const allowedPhases = computed(() => page.props.allowedPhases ?? ['before']);
const isSports = computed(() => page.props.event?.event_type === 'sports');
const phases = REPORT_PHASES.map((p) => ({ ...p, shortLabel: p.label.replace(' event', '') }));

function isPhaseAllowed(key) {
    return allowedPhases.value.includes(key);
}
</script>
