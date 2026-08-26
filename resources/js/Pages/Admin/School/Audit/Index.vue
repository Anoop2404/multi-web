<template>
    <SchoolAdminLayout title="Activity log" :school="school" :show-header-title="false">
        <PageHeader
            title="School activity log"
            eyebrow="Security & compliance"
            description="Detailed change history for students, teachers, membership records, board results, and other school actions."
        >
            <template #actions>
                <a :href="exportUrl" class="btn-secondary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
            <button v-for="(count, key) in logNameSummary" :key="key" type="button"
                    class="card card--muted text-left !py-3 transition hover:border-[#6366f1]/40"
                    :class="localFilters.log_name === key ? 'ring-2 ring-[#6366f1]/30 border-[#6366f1]/40' : ''"
                    @click="toggleLogName(key)">
                <p class="text-xs uppercase font-bold text-slate-500 tracking-wide">{{ labelForLogName(key) }}</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ count }}</p>
            </button>
        </div>

        <div class="card mb-4 flex flex-wrap gap-2 items-end">
            <div>
                <label class="form-label">Log type</label>
                <SearchableSelect
                    v-model="localFilters.log_name"
                    :options="logNameOptions"
                    :all-option="true"
                    all-label="All logs"
                    class="min-w-[11rem]"
                />
            </div>
            <div>
                <label class="form-label">Action</label>
                <input v-model="localFilters.action" class="field text-sm min-w-[10rem]" placeholder="e.g. updated">
            </div>
            <div>
                <label class="form-label">From</label>
                <input v-model="localFilters.from" type="date" class="field text-sm">
            </div>
            <div>
                <label class="form-label">To</label>
                <input v-model="localFilters.to" type="date" class="field text-sm">
            </div>
            <div class="flex-1 min-w-[12rem]">
                <label class="form-label">Search</label>
                <input v-model="localFilters.q" class="field text-sm w-full" placeholder="Student, teacher, IP, description…">
            </div>
            <button type="button" class="btn-secondary text-sm" @click="clearFilters">Clear</button>
        </div>

        <div v-if="Object.keys(actionSummary).length" class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold text-slate-500 self-center">Top actions:</span>
            <button v-for="(count, action) in actionSummary" :key="action" type="button"
                    class="text-xs px-2 py-1 rounded-full border border-slate-200 bg-white hover:bg-slate-50"
                    @click="filterAction(action)">
                {{ action }} ({{ count }})
            </button>
        </div>

        <DetailedLogTable :logs="logs" />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import DetailedLogTable from '@/Components/logs/DetailedLogTable.vue';

const props = defineProps({
    school: { type: Object, required: true },
    logs: { type: Object, default: () => ({ data: [], links: [] }) },
    logNameSummary: { type: Object, default: () => ({}) },
    actionSummary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    exportUrl: { type: String, default: '' },
});

const localFilters = reactive({
    log_name: props.filters.log_name ?? '',
    action: props.filters.action ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    q: props.filters.q ?? '',
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(localFilters).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });

    const qs = params.toString();
    return `/school-admin/${props.school.id}/audit-logs/export${qs ? `?${qs}` : ''}`;
});

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/audit-logs`, { ...localFilters }, { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(localFilters, applyFilters, () => props.filters);

function clearFilters() {
    Object.keys(localFilters).forEach((k) => { localFilters[k] = ''; });
    applyFilters();
}

function toggleLogName(key) {
    localFilters.log_name = localFilters.log_name === key ? '' : key;
    applyFilters();
}

function filterAction(action) {
    localFilters.action = action;
    applyFilters();
}

function labelForLogName(key) {
    if (!key) return 'General';
    return key
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, (m) => m.toUpperCase());
}

const logNameOptions = computed(() => Object.entries(props.logNameSummary).map(([key, count]) => ({
    value: key,
    label: `${labelForLogName(key)} (${count})`,
})));
</script>
