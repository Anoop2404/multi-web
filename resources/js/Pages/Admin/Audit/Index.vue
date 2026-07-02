<template>
    <AdminLayout title="Audit Log">
        <PageHeader
            title="Platform audit log"
            eyebrow="Security & compliance"
            description="Feature-wise activity across authentication, membership, fest operations, and admin actions."
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

        <form class="card mb-4 flex flex-wrap gap-2 items-end" @submit.prevent="applyFilters">
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
                <input v-model="localFilters.q" class="field text-sm w-full" placeholder="Email, IP, description…">
            </div>
            <button type="submit" class="btn-primary text-sm">Filter</button>
            <button type="button" class="btn-secondary text-sm" @click="clearFilters">Clear</button>
        </form>

        <div v-if="Object.keys(actionSummary).length" class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold text-slate-500 self-center">Top actions:</span>
            <button v-for="(count, action) in actionSummary" :key="action" type="button"
                    class="text-xs px-2 py-1 rounded-full border border-slate-200 bg-white hover:bg-slate-50"
                    @click="filterAction(action)">
                {{ action }} ({{ count }})
            </button>
        </div>

        <p class="text-xs text-slate-500 mb-3">Showing up to 200 of {{ total }} matching entries</p>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Feature</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">User / email</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="log in logs" :key="log.id">
                        <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">{{ log.created_at }}</td>
                        <td class="px-4 py-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded"
                                  :class="categoryClass(log.category)">
                                {{ log.category_label }}
                            </span>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ log.action }}</td>
                        <td class="px-4 py-2">{{ log.description }}</td>
                        <td class="px-4 py-2">
                            <p class="font-medium">{{ log.user?.name || '—' }}</p>
                            <p class="text-xs text-gray-400">{{ log.email || log.user?.email || '—' }}</p>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-400">{{ log.ip_address || '—' }}</td>
                    </tr>
                    <tr v-if="!logs.length">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No audit entries for these filters</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    logs: Array,
    summary: { type: Object, default: () => ({}) },
    actionSummary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Object, default: () => ({}) },
    total: { type: Number, default: 0 },
});

const localFilters = reactive({
    category: props.filters.category ?? '',
    action: props.filters.action ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    q: props.filters.q ?? '',
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(localFilters).forEach(([k, v]) => {
        if (v) params.set(k, v);
    });
    const qs = params.toString();
    return `/admin/audit-logs/export${qs ? `?${qs}` : ''}`;
});

function applyFilters() {
    router.get('/admin/audit-logs', { ...localFilters }, { preserveState: true, replace: true });
}

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

function categoryClass(category) {
    return {
        auth: 'bg-violet-50 text-violet-700',
        users: 'bg-blue-50 text-blue-700',
        membership: 'bg-emerald-50 text-emerald-700',
        fest: 'bg-amber-50 text-amber-800',
        finance: 'bg-indigo-50 text-indigo-700',
        system: 'bg-slate-100 text-slate-600',
    }[category] ?? 'bg-slate-100 text-slate-600';
}
</script>
