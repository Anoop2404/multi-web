<template>
    <SahodayaEventsLayout :title="`${event.title} — Item-wise`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item-wise report`" eyebrow="Reports"
                    description="Every item's registered students, in one table — filter by phase, region, category, or search.">
            <template #actions>
                <a :href="exportUrl" target="_blank" rel="noopener" class="btn-secondary text-sm">Download CSV ↓</a>
                <a :href="pdfExportUrl" target="_blank" rel="noopener" class="btn-primary text-sm">Download PDF ↓</a>
            </template>
        </PageHeader>

        <div class="card mb-4 !py-3">
            <label class="text-xs font-semibold text-slate-600 flex items-center gap-3">
                Prepared for (optional, shown on the PDF)
                <input v-model="forWhom" type="text" placeholder="e.g. District Kalotsav Committee" class="field text-sm w-72" />
            </label>
        </div>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="item-wise" />

        <!-- Server-scoped phase/region selector (phased_regional_billing roots) -->
        <div v-if="competitionPhases.length" class="card mb-4 !py-4">
            <div class="grid gap-3 md:grid-cols-3 items-end">
                <label class="text-xs font-semibold text-slate-600">Competition phase
                    <SearchableSelect v-model="scopePhaseId" :options="competitionPhases" :all-option="true" all-label="All published phases" class="mt-1 w-full" />
                </label>
                <label v-if="availableRegions.length" class="text-xs font-semibold text-slate-600">Region
                    <SearchableSelect v-model="scopeRegionId" :options="availableRegions" :all-option="true" all-label="Combined" class="mt-1 w-full" />
                </label>
                <button type="button" class="btn-primary text-sm" @click="applyReportScope">Apply</button>
            </div>
            <p class="mt-2 text-xs text-slate-500">Determines which registrations are loaded below — a region_admin/phase_admin only sees the phase/region they're assigned to.</p>
        </div>

        <!-- Simple region/sport-event switcher for non-phased partitioned events -->
        <div v-else-if="childEvents.length" class="card mb-4 !py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent" :options="childEventOptions" :all-option="false" class="text-xs w-64 font-semibold" />
            </div>
        </div>

        <!-- Client-side filters over the already-scoped dataset -->
        <div class="card mb-4 !py-3">
            <div class="grid gap-3 md:grid-cols-4">
                <label class="text-xs font-semibold text-slate-600">Category
                    <SearchableSelect v-model="categoryFilter" :options="categoryOptions" :all-option="true" all-label="All categories" class="mt-1 w-full" />
                </label>
                <label class="text-xs font-semibold text-slate-600">Mark status
                    <SearchableSelect v-model="markStatusFilter" :options="markStatusOptions" :all-option="true" all-label="All" class="mt-1 w-full" />
                </label>
                <label class="md:col-span-2 text-xs font-semibold text-slate-600">Search
                    <input v-model="search" type="text" placeholder="Item, school, or participant name…" class="field mt-1 text-sm w-full" />
                </label>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50/80 flex items-center justify-between flex-wrap gap-2">
                <h3 class="section-title text-sm mb-0">
                    {{ filteredRows.length }} registration{{ filteredRows.length === 1 ? '' : 's' }}
                    <span v-if="filteredRows.length > 0 && perPage !== 'all'" class="text-xs font-normal text-slate-500 ml-1">
                        (Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, filteredRows.length) }})
                    </span>
                </h3>
                <div v-if="filteredRows.length > 50" class="flex items-center gap-3">
                    <label class="text-xs text-slate-600 flex items-center gap-1.5">
                        Per page:
                        <select v-model="perPage" class="field text-xs !py-1 !px-2 rounded border-slate-300">
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                            <option :value="250">250</option>
                            <option :value="500">500</option>
                            <option value="all">All</option>
                        </select>
                    </label>
                    <div v-if="totalPages > 1" class="flex items-center gap-1 text-xs">
                        <button type="button" class="px-2.5 py-1 border rounded bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" :disabled="currentPage <= 1" @click="currentPage--">← Prev</button>
                        <span class="px-2 font-medium text-slate-700">Page {{ currentPage }} of {{ totalPages }}</span>
                        <button type="button" class="px-2.5 py-1 border rounded bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" :disabled="currentPage >= totalPages" @click="currentPage++">Next →</button>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th v-if="competitionPhases.length">Phase</th>
                            <th v-if="hasAnyRegion">Region</th>
                            <th>School</th>
                            <th>Participant</th>
                            <th>Reg no</th>
                            <th>Fest ID</th>
                            <th>Item reg</th>
                            <th>Chest</th>
                            <th>Status</th>
                            <th>Grade</th>
                            <th>Rank</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, idx) in paginatedRows" :key="r.id">
                            <td class="text-xs text-slate-400">{{ (perPage === 'all' ? 0 : (currentPage - 1) * perPage) + idx + 1 }}</td>
                            <td class="text-xs">{{ r.category_label }}</td>
                            <td class="font-medium">
                                {{ r.item_title }}
                                <span v-if="r.item_code" class="block font-mono text-xs text-slate-400">{{ r.item_code }}</span>
                            </td>
                            <td v-if="competitionPhases.length" class="text-xs">{{ r.phase_name ?? '—' }}</td>
                            <td v-if="hasAnyRegion" class="text-xs">{{ r.region_name ?? '—' }}</td>
                            <td class="text-xs">{{ (r.school_name || '').toUpperCase() }}</td>
                            <td class="font-medium">{{ r.participant }}</td>
                            <td class="font-mono text-xs">{{ r.reg_no ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ r.fest_id ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ r.item_reg ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ r.chest_no ?? '—' }}</td>
                            <td class="text-xs capitalize">{{ r.status }}</td>
                            <td>{{ r.grade ?? '—' }}</td>
                            <td>{{ r.position ?? '—' }}</td>
                            <td>{{ r.score ?? '—' }}</td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="15" class="p-8 text-center text-slate-400">No registrations match the current scope/filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="totalPages > 1" class="px-5 py-3 border-t bg-slate-50/50 flex items-center justify-between text-xs">
                <span class="text-slate-500">Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, filteredRows.length) }} of {{ filteredRows.length }} registrations</span>
                <div class="flex items-center gap-1">
                    <button type="button" class="px-2.5 py-1 border rounded bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" :disabled="currentPage <= 1" @click="currentPage--">← Prev</button>
                    <span class="px-2 font-medium text-slate-700">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button type="button" class="px-2.5 py-1 border rounded bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" :disabled="currentPage >= totalPages" @click="currentPage++">Next →</button>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filterHeadId: { type: [String, Number], default: null },
    filterItemId: { type: Number, default: null },
    xlsUrl: String,
    pdfUrl: String,
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    competitionPhases: { type: Array, default: () => [] },
    reportScopeSelection: { type: Object, default: () => ({}) },
});

const reportsBase = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`;

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/item-wise`);
}

const childEventOptions = computed(() => props.childEvents.map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })));

const scopePhaseId = ref(props.reportScopeSelection.competition_phase_id || '');
const scopeRegionId = ref(props.reportScopeSelection.region_id || '');

const availableRegions = computed(() => {
    if (!scopePhaseId.value) {
        return props.regions;
    }
    const phase = props.competitionPhases.find((p) => String(p.id) === String(scopePhaseId.value));
    if (!phase || !phase.is_regional || !phase.regions) {
        return [];
    }
    return phase.regions;
});

watch(availableRegions, (newRegions) => {
    if (!newRegions.length || (scopeRegionId.value && !newRegions.some((r) => String(r.id) === String(scopeRegionId.value)))) {
        scopeRegionId.value = '';
    }
});

const categoryFilter = ref(props.reportScopeSelection.category || '');
const markStatusFilter = ref(props.reportScopeSelection.mark_status || '');
const search = ref(props.reportScopeSelection.search || '');

function scopeParams() {
    return {
        scope_mode: scopeRegionId.value ? 'region' : 'combined',
        competition_phase_id: scopePhaseId.value || undefined,
        region_id: scopeRegionId.value || undefined,
        category: categoryFilter.value || undefined,
        mark_status: markStatusFilter.value || undefined,
        search: search.value || undefined,
    };
}

function applyReportScope() {
    router.get(`${reportsBase}/item-wise`, scopeParams(), { preserveState: false });
}

const forWhom = ref('');

function scopedUrl(baseUrl, extraParams = {}) {
    const params = new URLSearchParams(
        Object.entries({ ...scopeParams(), ...extraParams }).filter(([, v]) => v !== undefined && v !== ''),
    );
    const qs = params.toString();
    return qs ? `${baseUrl}?${qs}` : baseUrl;
}

const exportUrl = computed(() => scopedUrl(props.xlsUrl));
const pdfExportUrl = computed(() => scopedUrl(props.pdfUrl, { for_whom: forWhom.value }));

const categoryOptions = computed(() => props.categories.map((c) => ({ value: c.key, label: c.label })));
const markStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'marked', label: 'Marked' },
];

const hasAnyRegion = computed(() => props.rows.some((r) => r.region_name));

function isMarkPending(row) {
    return row.grade == null && row.position == null && row.score == null;
}

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.rows.filter((r) => {
        if (categoryFilter.value && r.category !== categoryFilter.value) return false;
        if (markStatusFilter.value === 'pending' && !isMarkPending(r)) return false;
        if (markStatusFilter.value === 'marked' && isMarkPending(r)) return false;
        if (!term) return true;

        return [r.item_title, r.school_name, r.participant]
            .filter(Boolean)
            .some((v) => v.toLowerCase().includes(term));
    });
});

const perPage = ref(100);
const currentPage = ref(1);

watch([categoryFilter, markStatusFilter, search, scopePhaseId, scopeRegionId], () => {
    currentPage.value = 1;
});

watch(perPage, () => {
    currentPage.value = 1;
});

const totalPages = computed(() => {
    if (perPage.value === 'all' || !perPage.value) return 1;
    return Math.ceil(filteredRows.value.length / perPage.value) || 1;
});

const paginatedRows = computed(() => {
    if (perPage.value === 'all' || !perPage.value) {
        return filteredRows.value;
    }
    const start = (currentPage.value - 1) * perPage.value;
    return filteredRows.value.slice(start, start + perPage.value);
});
</script>
