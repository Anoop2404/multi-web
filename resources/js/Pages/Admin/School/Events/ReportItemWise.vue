<template>
    <SchoolAdminLayout :title="`Item-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Item-wise — ${event.title}`"
            :eyebrow="programLabel"
            description="Every item your school has registered for, in one table — filter by phase, region, category, or search."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <a :href="csvUrl" target="_blank" rel="noopener" class="btn-secondary text-sm">Download CSV ↓</a>
                <a :href="pdfExportUrl" target="_blank" rel="noopener" class="btn-primary text-sm">Download PDF ↓</a>
            </template>
        </PageHeader>

        <div class="card mb-4 !py-3">
            <label class="text-xs font-semibold text-slate-600 flex items-center gap-3">
                Prepared for (optional, shown on the PDF)
                <input v-model="forWhom" type="text" placeholder="e.g. School Principal" class="field text-sm w-72" />
            </label>
        </div>

        <div class="card mb-4 !py-3">
            <div class="grid gap-3 md:grid-cols-3">
                <label v-if="usesPhases" class="text-xs font-semibold text-slate-600">Phase
                    <SearchableSelect v-model="phaseFilter" class="mt-1 w-full" :options="phases" :all-option="true" all-label="All phases" />
                </label>
                <label v-if="hasAnyRegion" class="text-xs font-semibold text-slate-600">Region
                    <SearchableSelect v-model="regionFilter" class="mt-1 w-full" :options="regionOptions" :all-option="true" all-label="All regions" />
                </label>
                <label class="text-xs font-semibold text-slate-600">Category
                    <SearchableSelect v-model="categoryFilter" class="mt-1 w-full" :options="categoryOptions" :all-option="true" all-label="All categories" />
                </label>
                <label class="text-xs font-semibold text-slate-600">Mark status
                    <SearchableSelect v-model="markStatusFilter" class="mt-1 w-full" :options="markStatusOptions" :all-option="true" all-label="All" />
                </label>
                <label class="text-xs font-semibold text-slate-600 md:col-span-3">Search
                    <input v-model="search" type="text" placeholder="Item or participant name…" class="field mt-1 text-sm w-full" />
                </label>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm">{{ filteredRows.length }} registration{{ filteredRows.length === 1 ? '' : 's' }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Category</th>
                            <th>Item</th>
                            <th v-if="usesPhases">Phase</th>
                            <th v-if="hasAnyRegion">Region</th>
                            <th>School</th>
                            <th>Participant</th>
                            <th>Reg no</th>
                            <th>Fest ID</th>
                            <th>Item reg</th>
                            <th>Grade</th>
                            <th>Rank</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, idx) in filteredRows" :key="r.id">
                            <td class="text-xs text-slate-400">{{ idx + 1 }}</td>
                            <td class="text-xs">{{ r.category_label }}</td>
                            <td class="font-medium">
                                {{ r.item_title }}
                                <span v-if="r.item_code" class="block font-mono text-xs text-slate-400">{{ r.item_code }}</span>
                            </td>
                            <td v-if="usesPhases" class="text-xs">{{ r.phase_name ?? '—' }}</td>
                            <td v-if="hasAnyRegion" class="text-xs">{{ r.region_name ?? '—' }}</td>
                            <td class="text-xs">{{ (r.school_name || '').toUpperCase() }}</td>
                            <td>{{ r.participant }}</td>
                            <td class="font-mono text-xs">{{ r.reg_no ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ r.fest_id ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ r.item_reg ?? '—' }}</td>
                            <td>{{ r.grade ?? '—' }}</td>
                            <td>{{ r.position ?? '—' }}</td>
                            <td>{{ r.score ?? '—' }}</td>
                        </tr>
                        <tr v-if="!filteredRows.length"><td colspan="13" class="p-8 text-center text-slate-400">No registrations match the current filters.</td></tr>
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
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    usesPhases: { type: Boolean, default: false },
    csvUrl: String,
    pdfUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);

const phaseFilter = ref('');
const regionFilter = ref('');
const categoryFilter = ref('');
const markStatusFilter = ref('');
const search = ref('');
const forWhom = ref('');
const markStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'marked', label: 'Marked' },
];

const pdfExportUrl = computed(() => {
    if (!forWhom.value.trim()) return props.pdfUrl;
    return `${props.pdfUrl}?${new URLSearchParams({ for_whom: forWhom.value }).toString()}`;
});

const phases = computed(() => [...new Set(props.rows.map((r) => r.phase_name).filter(Boolean))]);
const regionOptions = computed(() => [...new Set(props.rows.map((r) => r.region_name).filter(Boolean))]);
const hasAnyRegion = computed(() => regionOptions.value.length > 0);
const categoryOptions = computed(() => props.categories.map((c) => ({ value: c.key, label: c.label })));

function isMarkPending(row) {
    return row.grade == null && row.position == null && row.score == null;
}

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.rows.filter((r) => {
        if (phaseFilter.value && r.phase_name !== phaseFilter.value) return false;
        if (regionFilter.value && r.region_name !== regionFilter.value) return false;
        if (categoryFilter.value && r.category !== categoryFilter.value) return false;
        if (markStatusFilter.value === 'pending' && !isMarkPending(r)) return false;
        if (markStatusFilter.value === 'marked' && isMarkPending(r)) return false;
        if (!term) return true;

        return [r.item_title, r.participant].filter(Boolean).some((v) => v.toLowerCase().includes(term));
    });
});
</script>
