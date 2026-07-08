<template>
    <section class="mb-8">
        <h3 class="section-title mb-3">Download packs</h3>
        <p class="text-sm text-slate-600 mb-4">Bulk exports grouped by event phase. Unlocked packs match your current timeline.</p>
        <div class="reports-phase-grid">
            <Link v-for="phase in phases" :key="phase.key"
                  :href="phaseHref(phase.key)"
                  class="reports-phase-card group"
                  :class="{
                      'reports-phase-card--current': currentPhase === phase.key,
                      'reports-phase-card--locked': !isAllowed(phase.key),
                  }">
                <span class="text-2xl mb-2">{{ phase.icon }}</span>
                <p class="font-semibold text-slate-900">{{ phase.label }}</p>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ phase.hint }}</p>
                <p class="mt-3 text-xs font-semibold text-indigo-600">
                    {{ isAllowed(phase.key) ? 'Open pack →' : 'Not yet available' }}
                </p>
            </Link>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { REPORT_PHASES } from '@/support/festReportCatalog.js';

const props = defineProps({
    reportsBase: { type: String, required: true },
    currentPhase: { type: String, default: 'before' },
    allowedPhases: { type: Array, default: () => ['before'] },
});

const phases = REPORT_PHASES;

function phaseHref(key) {
    return `${props.reportsBase}/downloads/${key}`;
}

function isAllowed(key) {
    return (props.allowedPhases ?? []).includes(key);
}
</script>
