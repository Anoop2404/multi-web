<template>
    <SahodayaAdminLayout :title="report.label" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="report.label" eyebrow="Reports"
                    :description="`${report.id} · ${preview.total} row(s)`">
            <template #actions>
                <Link :href="hubUrl" class="btn-secondary text-sm">← Reports hub</Link>
                <a :href="exportLink" class="btn-primary text-sm">Export CSV</a>
            </template>
        </PageHeader>

        <form v-if="meta.filters?.length" class="card !p-4 mb-4" @submit.prevent="applyFilters">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <FormField v-for="f in meta.filters" :key="f.key" :label="f.label">
                    <select v-if="f.type === 'select'"
                            v-model="filterForm[f.key]"
                            class="field"
                            :disabled="f.key === 'head_id' && !filterForm.event_id">
                        <option value="">{{ selectPlaceholder(f.key) }}</option>
                        <option v-for="opt in optionsFor(f.key)" :key="opt.id" :value="String(opt.id)">
                            {{ opt.label }}
                        </option>
                    </select>
                    <input v-else
                           v-model="filterForm[f.key]"
                           :type="f.type === 'date' ? 'date' : 'text'"
                           class="field">
                </FormField>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm">Apply</button>
                    <button type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                </div>
            </div>
            <p v-if="hasHeadFilter && !filterForm.event_id" class="text-xs text-slate-500 mt-2">
                Select an event first to filter by item head.
            </p>
        </form>

        <div class="card overflow-x-auto">
            <table v-if="preview.rows?.length" class="data-table text-sm">
                <thead>
                    <tr>
                        <th v-for="col in meta.columns" :key="col.key">{{ col.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in preview.rows" :key="i">
                        <td v-for="col in meta.columns" :key="col.key">{{ row[col.key] ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-500 p-4">No rows match the current filters.</p>
        </div>

        <div v-if="preview.total > preview.per_page" class="flex justify-center gap-2 mt-4">
            <Link v-if="preview.page > 1" :href="pageLink(preview.page - 1)" class="btn-secondary text-sm">Previous</Link>
            <span class="text-sm text-slate-600 self-center">Page {{ preview.page }} of {{ totalPages }}</span>
            <Link v-if="preview.page < totalPages" :href="pageLink(preview.page + 1)" class="btn-secondary text-sm">Next</Link>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    report: Object,
    meta: Object,
    preview: Object,
    filters: Object,
    filterOptions: { type: Object, default: () => ({}) },
    exportUrl: String,
    hubUrl: String,
});

const filterForm = reactive(initFilterForm());

function initFilterForm() {
    const form = {};
    for (const f of props.meta?.filters ?? []) {
        form[f.key] = props.filters?.[f.key] ?? '';
    }
    return form;
}

const hasHeadFilter = computed(() => (props.meta?.filters ?? []).some(f => f.key === 'head_id'));

const totalPages = computed(() => Math.max(1, Math.ceil(props.preview.total / props.preview.per_page)));

const exportLink = computed(() => {
    const params = new URLSearchParams(activeFilters());
    const qs = params.toString();
    return qs ? `${props.exportUrl}?${qs}` : props.exportUrl;
});

function optionsFor(key) {
    return props.filterOptions?.[key] ?? [];
}

function selectPlaceholder(key) {
    return {
        event_id: 'All events',
        school_id: 'All schools',
        head_id: 'All item heads',
        exam_id: 'All exams',
    }[key] ?? 'All';
}

function activeFilters() {
    const out = {};
    for (const [key, value] of Object.entries(filterForm)) {
        if (value !== '' && value != null) {
            out[key] = value;
        }
    }
    return out;
}

function applyFilters() {
    router.get(window.location.pathname, { ...activeFilters(), page: 1 }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilters() {
    for (const f of props.meta?.filters ?? []) {
        filterForm[f.key] = '';
    }
    applyFilters();
}

function pageLink(page) {
    const params = new URLSearchParams({ ...activeFilters(), page: String(page) });
    return `${window.location.pathname}?${params.toString()}`;
}

watch(() => filterForm.event_id, (next, prev) => {
    if (!hasHeadFilter.value || next === prev) {
        return;
    }
    if (prev === undefined && !next) {
        return;
    }
    filterForm.head_id = '';
    router.get(window.location.pathname, { ...activeFilters(), page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        only: ['filterOptions', 'filters', 'preview', 'meta'],
    });
});
</script>
