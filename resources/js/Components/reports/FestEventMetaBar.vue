<template>
    <div class="reports-meta-strip" :class="{ '!p-3': compact }">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Event timeline</p>
                <p v-if="meta.event_dates_label" class="text-base font-semibold text-slate-900 mt-0.5">{{ meta.event_dates_label }}</p>
                <p v-else class="text-sm text-amber-700 mt-0.5">Fest dates not set</p>
                <p v-if="meta.venue && !compact" class="text-xs text-slate-500 mt-1">📍 {{ meta.venue }}</p>
            </div>
            <div class="flex flex-wrap gap-1.5 justify-end">
                <span class="status-pill text-xs">{{ meta.status_label ?? meta.status }}</span>
                <span class="status-pill text-xs" :class="meta.results_published ? 'status-pill--published' : 'status-pill--open'">
                    {{ meta.results_published ? 'Results out' : 'Results pending' }}
                </span>
                <span v-if="meta.schedule_published" class="status-pill text-xs bg-sky-50 text-sky-800 border-sky-200">Schedule live</span>
                <span v-if="meta.report_phase" class="status-pill text-xs capitalize">{{ meta.report_phase }} phase</span>
            </div>
        </div>
        <dl v-if="!compact" class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm border-t border-slate-200/80 pt-4">
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Start</dt>
                <dd class="font-medium mt-0.5">{{ formatDate(meta.event_start) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">End</dt>
                <dd class="font-medium mt-0.5">{{ formatDate(meta.event_end) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Registration</dt>
                <dd class="font-medium mt-0.5">{{ meta.registration_window_label ?? '—' }}</dd>
            </div>
            <div v-if="meta.sports_age_cutoff_date">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Age cutoff</dt>
                <dd class="font-medium mt-0.5">{{ formatDate(meta.sports_age_cutoff_date) }}</dd>
            </div>
        </dl>
        <p v-if="showEditHint && !meta.event_start && !compact" class="text-xs text-amber-700 mt-3 pt-3 border-t border-amber-100">
            Set fest dates on
            <Link v-if="meta.overview_url" :href="meta.overview_url" class="font-semibold underline">event overview</Link>
            for correct age groups and report phases.
        </p>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    meta: { type: Object, required: true },
    showEditHint: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
