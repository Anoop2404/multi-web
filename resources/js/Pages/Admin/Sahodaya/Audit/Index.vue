<template>
    <SahodayaAdminLayout title="Activity log" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="Sahodaya activity log"
            eyebrow="Security & compliance"
            description="Detailed log trail across Sahodaya administration, member schools, fest operations, MCQ, training, and finance."
        >
            <template #actions>
                <a :href="exportUrl" class="btn-secondary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
            <button v-for="(label, key) in categories" :key="key" type="button"
                    class="card card--muted text-left !py-3 transition hover:border-[#6366f1]/40"
                    :class="localFilters.category === key ? 'ring-2 ring-[#6366f1]/30 border-[#6366f1]/40' : ''"
                    @click="toggleCategory(key)">
                <p class="text-xs uppercase font-bold text-slate-500 tracking-wide">{{ label }}</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ summary[key] ?? 0 }}</p>
            </button>
        </div>

        <div class="card mb-4 flex flex-wrap gap-2 items-end">
            <div>
                <label class="form-label">School</label>
                <select v-model="localFilters.school_id" class="field text-sm min-w-[11rem]">
                    <option value="">All schools</option>
                    <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Category</label>
                <select v-model="localFilters.category" class="field text-sm min-w-[10rem]">
                    <option value="">All categories</option>
                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Action</label>
                <input v-model="localFilters.action" class="field text-sm min-w-[10rem]" placeholder="e.g. login.failed">
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
                <input v-model="localFilters.q" class="field text-sm w-full" placeholder="User, IP, description…">
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
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DetailedLogTable from '@/Components/logs/DetailedLogTable.vue';

const props = defineProps({
    sahodaya: { type: Object, required: true },
    publicUrl: { type: String, default: null },
    pendingPaymentsCount: { type: Number, default: 0 },
    logs: { type: Object, default: () => ({ data: [], links: [] }) },
    summary: { type: Object, default: () => ({}) },
    actionSummary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Object, default: () => ({}) },
    schools: { type: Array, default: () => [] },
    exportUrl: { type: String, default: '' },
});

const localFilters = reactive({
    category: props.filters.category ?? '',
    action: props.filters.action ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    school_id: props.filters.school_id ?? '',
    q: props.filters.q ?? '',
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(localFilters).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });

    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/audit-logs/export${qs ? `?${qs}` : ''}`;
});

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/audit-logs`, { ...localFilters }, { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(localFilters, applyFilters, () => props.filters);

function clearFilters() {
    Object.keys(localFilters).forEach((k) => { localFilters[k] = ''; });
    applyFilters();
}

function toggleCategory(key) {
    localFilters.category = localFilters.category === key ? '' : key;
    applyFilters();
}

function filterAction(action) {
    localFilters.action = action;
    applyFilters();
}
</script>
