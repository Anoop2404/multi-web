<template>
    <SchoolAdminLayout :title="`${programLabel} — Select Event for Reports`" :school="school" :show-header-title="false">
        <div class="reports-shell">
            <PageHeader :title="`${programLabel} — Select Event for Reports`" :eyebrow="`${programLabel} Reports`"
                        description="Select an event below to open its full reports catalog (admit cards, chest numbers, score sheets, and exports)." />

            <div v-if="events.length" class="space-y-4">
                <article v-for="ev in events" :key="ev.id" class="reports-event-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-900">{{ ev.title }}</h3>
                            <p v-if="ev.event_dates_label" class="text-sm text-slate-600 mt-0.5">{{ ev.event_dates_label }}</p>
                            <p v-else-if="ev.event_start" class="text-sm text-slate-500 mt-0.5">{{ formatDate(ev.event_start) }}</p>
                            <p v-else class="text-sm text-amber-600 mt-0.5">Fest dates not set</p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            <span class="status-pill text-[10px] capitalize">{{ ev.status?.replace(/_/g, ' ') }}</span>
                            <span v-if="ev.results_published" class="status-pill status-pill--published text-[10px]">Results out</span>
                            <span v-else class="status-pill text-[10px]">Results pending</span>
                            <span v-if="ev.schedule_published" class="status-pill text-[10px] bg-sky-50 text-sky-800 border-sky-200">Schedule live</span>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-slate-600">
                        {{ reportCount }} reports available
                        <span class="text-slate-400">·</span>
                        {{ categoryPreview }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <Link :href="hubHref(ev)" class="btn-primary text-sm">
                            View all reports →
                        </Link>
                        <Link v-if="isSports" :href="`${programBase}/registration?event=${ev.id}`" class="btn-secondary text-xs !min-h-0">
                            Step 1 · Event registration
                        </Link>
                        <Link v-if="isSports" :href="`${programBase}/events/${ev.id}/items`" class="btn-secondary text-xs !min-h-0">
                            Step 2 · Register by Sport Event
                        </Link>
                    </div>
                </article>
            </div>

            <EmptyState v-else title="No events yet" description="Events will appear here once Sahodaya publishes them for your program." icon="📊" />
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import {
    REPORT_CATEGORY_ORDER,
    schoolReportsForProgram,
    reportCategoryLabel,
} from '@/support/festReportCatalog.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    events: Array,
});

const { programSlug, programLabel, programBase } = useSchoolProgramContext(props);

const isSports = computed(() => props.programMeta?.eventType === 'sports' || programSlug.value === 'sports-meet');

const allReports = computed(() => schoolReportsForProgram(programSlug.value));

const reportCount = computed(() => allReports.value.length);

const categoryPreview = computed(() =>
    REPORT_CATEGORY_ORDER
        .filter((key) => allReports.value.some((r) => r.category === key))
        .map((key) => reportCategoryLabel(key, isSports.value))
        .join(' · '),
);

function hubHref(ev) {
    return `${programBase.value}/reports/${ev.id}`;
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
