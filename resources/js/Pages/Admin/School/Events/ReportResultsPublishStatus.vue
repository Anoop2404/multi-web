<template>
    <SchoolAdminLayout :title="`Results publish status — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Results publish status — ${event.title}`" :eyebrow="programLabel"
                    description="Which of your items have published results — filter by head, with competition dates.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <Link :href="`${programBase}/reports/${event.id}/published-results`" class="btn-secondary text-sm">Published results →</Link>
            </template>
        </PageHeader>

        <section v-if="hasItemHeads" class="mb-6">
            <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Filter by item</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Search or pick an item — the table below updates instantly.</p>
                </div>
                <Link :href="`${programBase}/reports/${event.id}`" class="text-xs font-semibold text-indigo-600 hover:underline">← All sections</Link>
            </div>
            <ReportItemSearchSelect :items="flatItems"
                                    :model-value="itemFilter"
                                    :all-items-label="`All ${flatItems.length} items`"
                                    search-placeholder="Search by item name or code…"
                                    @select="onItemSelect" />
        </section>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Your items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ filteredSummary.published }}</p>
                <p class="text-xs text-slate-500 mt-1">Published</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ filteredSummary.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Awaiting publish</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <button v-for="opt in statusFilters" :key="opt.id" type="button"
                    class="text-xs px-3 py-1.5 rounded-full border transition-colors"
                    :class="statusFilter === opt.id
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300'"
                    @click="statusFilter = opt.id">
                {{ opt.label }}
            </button>
        </div>

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="pl-5">Head</th>
                            <th>Item</th>
                            <th>Details</th>
                            <th>Competition window</th>
                            <th>Marks</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in displayRows" :key="row.item_id">
                            <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50/80">
                                <td colspan="7" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ row.head_name ?? 'Other items' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="pl-5 text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                                <td class="font-medium">
                                    {{ row.title }}
                                    <p v-if="row.item_code" class="text-xs font-mono text-slate-400 mt-0.5">{{ row.item_code }}</p>
                                </td>
                                <td class="text-xs text-slate-600">
                                    <span v-if="row.age_group">{{ row.age_group }}</span>
                                    <span v-if="row.sport_discipline"> · {{ row.sport_discipline }}</span>
                                    <span v-if="!row.age_group && !row.sport_discipline">—</span>
                                </td>
                                <td class="text-sm text-slate-600">{{ formatWindow(row) }}</td>
                                <td class="text-sm">
                                    <span :class="row.marks_ready ? 'text-emerald-700' : 'text-slate-500'">
                                        {{ row.marks_entered }}/{{ row.performers ?? (row.marks_entered + row.marks_pending) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill text-xs"
                                          :class="row.results_published ? 'status-pill--published' : 'status-pill--open'">
                                        {{ row.results_published ? 'Published' : 'Not published' }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!displayRows.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    summary: Object,
    rows: Array,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/results-publish-status`;

const statusFilter = ref('all');
const statusFilters = [
    { id: 'all', label: 'All' },
    { id: 'published', label: 'Published' },
    { id: 'pending', label: 'Not published' },
];

const {
    headFilter,
    itemFilter,
    headItemGroups,
    hasItemHeads,
} = useReportHeadFilters(base, () => props.rows ?? []);

const flatItems = computed(() => headItemGroups.value.flatMap((h) => h.items ?? []));

// Client-side only, like the rest of this page's filtering — resultsPublishStatus()
// already returns every one of the school's items unfiltered, so picking an item
// just narrows displayRows in-memory (no server round trip needed).
function onItemSelect(itemId) {
    itemFilter.value = itemId;
}

const headScopedRows = computed(() => {
    let rows = props.rows ?? [];
    const head = headFilter.value;
    if (!head) return rows;
    if (head === 'other') return rows.filter((r) => r.head_id == null);
    return rows.filter((r) => String(r.head_id) === String(head));
});

const itemScopedRows = computed(() => {
    const rows = headScopedRows.value;
    const item = itemFilter.value;
    if (!item) return rows;
    return rows.filter((r) => String(r.item_id) === String(item));
});

const displayRows = computed(() => {
    let rows = itemScopedRows.value;
    if (statusFilter.value === 'published') {
        rows = rows.filter((r) => r.results_published);
    } else if (statusFilter.value === 'pending') {
        rows = rows.filter((r) => !r.results_published);
    }
    return rows;
});

const filteredSummary = computed(() => ({
    items: displayRows.value.length,
    published: displayRows.value.filter((r) => r.results_published).length,
    pending: displayRows.value.filter((r) => !r.results_published).length,
}));

function shouldShowHeadDivider(row, prev) {
    if (!prev) return true;
    return (row.head_id ?? null) !== (prev.head_id ?? null);
}

function formatWindow(row) {
    const start = row.competition_start;
    const end = row.competition_end;
    if (start && end) return `${formatDate(start)} – ${formatDate(end)}`;
    if (start) return `from ${formatDate(start)}`;
    if (end) return `until ${formatDate(end)}`;
    return '—';
}

function formatDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
