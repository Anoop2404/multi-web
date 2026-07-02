<template>
    <SahodayaAdminLayout title="Ledger Reports" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Ledger reports" eyebrow="Finance"
                    description="Summaries by account category, head, and month. Filter membership vs events vs training.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/ledger`" class="btn-secondary text-sm">← Ledger</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="applyFilter" class="card mb-6 flex flex-wrap items-end gap-3">
            <FormField label="From">
                <template #default="{ id }">
                    <input :id="id" v-model="from" type="date" class="field">
                </template>
            </FormField>
            <FormField label="To">
                <template #default="{ id }">
                    <input :id="id" v-model="to" type="date" class="field">
                </template>
            </FormField>
            <FormField label="Category">
                <template #default="{ id }">
                    <select :id="id" v-model="category" class="field">
                        <option value="">All categories</option>
                        <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </template>
            </FormField>
            <FormField label="Academic year">
                <template #default="{ id }">
                    <select :id="id" v-model="financialYearId" class="field">
                        <option value="">All years</option>
                        <option v-for="y in academicYears" :key="y.id" :value="String(y.id)">
                            {{ y.label }} ({{ y.status }})
                        </option>
                    </select>
                </template>
            </FormField>
            <button type="submit" class="btn-primary">Apply</button>
            <button type="button" @click="clearFilter" class="btn-secondary">Clear</button>
            <a :href="exportUrl" class="btn-primary ml-auto text-sm">Export CSV ↓</a>
        </form>

        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <div class="form-section overflow-hidden !p-0 md:col-span-1">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">By category</h3>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Type</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in byCategory" :key="i">
                                <td>{{ categoryLabels[row.category] ?? row.category }}</td>
                                <td>
                                    <span :class="row.entry_type === 'credit' ? 'text-emerald-700' : 'text-red-600'"
                                          class="font-semibold text-xs capitalize">{{ row.entry_type }}</span>
                                </td>
                                <td class="text-right font-mono">₹{{ fmt(row.total) }}</td>
                            </tr>
                            <tr v-if="!byCategory.length"><td colspan="3" class="p-6 text-center text-slate-400">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-section overflow-hidden !p-0 md:col-span-2">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">Fest event account heads</h3>
                <div class="overflow-x-auto max-h-64">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Event account</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in eventHeads" :key="h.id">
                                <td class="font-mono text-xs">{{ h.code }}</td>
                                <td>{{ h.name }}</td>
                                <td class="text-right">
                                    <Link v-if="h.event_id"
                                          :href="`/sahodaya-admin/${sahodaya.id}/events/${h.event_id}/fees/ledger`"
                                          class="text-indigo-600 text-xs font-semibold">Ledger →</Link>
                                </td>
                            </tr>
                            <tr v-if="!eventHeads.length">
                                <td colspan="3" class="p-6 text-center text-slate-400 text-xs">No per-event heads yet — approve event fees to create them</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="sportsHeads?.length" class="form-section overflow-hidden !p-0 md:col-span-2">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">Sports meet account heads</h3>
                <div class="overflow-x-auto max-h-64">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Sports account</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in sportsHeads" :key="h.id">
                                <td class="font-mono text-xs">{{ h.code }}</td>
                                <td>{{ h.name }}</td>
                                <td class="text-right">
                                    <Link v-if="h.event_id"
                                          :href="`/sahodaya-admin/${sahodaya.id}/events/${h.event_id}/fees/ledger`"
                                          class="text-indigo-600 text-xs font-semibold">Ledger →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="form-section overflow-hidden !p-0">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">By account head</h3>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Head</th>
                                <th>Type</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in byHead" :key="i">
                                <td>
                                    {{ row.name }}
                                    <span class="text-slate-400 text-xs block font-mono">{{ row.code }}</span>
                                </td>
                                <td>
                                    <span :class="row.entry_type === 'credit' ? 'text-emerald-700' : 'text-red-600'" class="font-semibold text-xs capitalize">
                                        {{ row.entry_type }}
                                    </span>
                                </td>
                                <td class="text-right font-mono">₹{{ fmt(row.total) }}</td>
                            </tr>
                            <tr v-if="!byHead.length"><td colspan="3" class="p-6 text-center text-slate-400">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-section overflow-hidden !p-0">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">Monthly summary</h3>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Type</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in monthly" :key="i">
                                <td class="font-mono text-xs">{{ row.month }}</td>
                                <td>
                                    <span :class="row.entry_type === 'credit' ? 'text-emerald-700' : 'text-red-600'" class="font-semibold text-xs capitalize">
                                        {{ row.entry_type }}
                                    </span>
                                </td>
                                <td class="text-right font-mono">₹{{ fmt(row.total) }}</td>
                            </tr>
                            <tr v-if="!monthly.length"><td colspan="3" class="p-6 text-center text-slate-400">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    byHead: Array, byCategory: Array, monthly: Array, eventHeads: Array, sportsHeads: { type: Array, default: () => [] },
    filterFrom: String, filterTo: String, filterCategory: String,
    categoryLabels: { type: Object, default: () => ({}) },
    academicYears: { type: Array, default: () => [] },
    filterFinancialYearId: { type: Number, default: null },
});

const from = ref(props.filterFrom ?? '');
const to   = ref(props.filterTo ?? '');
const category = ref(props.filterCategory ?? '');
const financialYearId = ref(props.filterFinancialYearId ? String(props.filterFinancialYearId) : '');

const exportUrl = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/ledger/export`;
    const params = new URLSearchParams();
    if (from.value) params.set('from', from.value);
    if (to.value)   params.set('to', to.value);
    if (category.value) params.set('category', category.value);
    if (financialYearId.value) params.set('financial_year_id', financialYearId.value);
    return params.toString() ? `${base}?${params}` : base;
});

function applyFilter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/ledger/reports`, {
        from: from.value || undefined,
        to: to.value || undefined,
        category: category.value || undefined,
        financial_year_id: financialYearId.value || undefined,
    }, { preserveScroll: true });
}

function clearFilter() {
    from.value = '';
    to.value   = '';
    category.value = '';
    financialYearId.value = '';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/ledger/reports`, {}, { preserveScroll: true });
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
