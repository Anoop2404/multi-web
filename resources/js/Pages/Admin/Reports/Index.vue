<template>
    <AdminLayout title="Reports">
        <div class="space-y-6">
            <p class="text-sm text-gray-600">
                Trends over platform activity. For raw, filterable activity records see
                <Link href="/admin/audit-logs" class="link-brand">Audit Log</Link>.
            </p>

            <div>
                <h3 class="font-semibold text-sm text-gray-700 mb-2">Subscriptions by status</h3>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div v-for="status in statusOrder" :key="status" class="stat-tile">
                        <p class="stat-tile-label">{{ statusLabels[status] }}</p>
                        <p class="stat-tile-value" :class="statusColors[status]">{{ subscriptionStatusBreakdown[status] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold text-sm text-gray-700 mb-2">Approved revenue by month</h3>
                <div class="card card--flush overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th v-for="row in revenueByMonth" :key="row.month" class="p-3 whitespace-nowrap">{{ row.month }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t">
                                <td v-for="row in revenueByMonth" :key="row.month" class="p-3 font-mono text-xs whitespace-nowrap">
                                    ₹{{ formatAmount(row.revenue_inr) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="font-semibold text-sm text-gray-700 mb-2">Platform snapshots</h3>
                <SahodayaDataTable :columns="snapshotColumns" :has-rows="!!snapshots.length" empty="No snapshots computed yet.">
                    <template #toolbar>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-500">One row per nightly <code>platform:snapshot-dashboard</code> run.</p>
                            <a href="/admin/reports/snapshots/export" class="btn-secondary text-xs">↓ Export XLSX</a>
                        </div>
                    </template>
                    <tr v-for="s in snapshots" :key="s.id">
                        <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">{{ formatDateTime(s.computed_at) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatAmount(s.total_students) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatAmount(s.total_teachers) }}</td>
                        <td class="px-4 py-3 text-right">₹{{ formatAmount(s.revenue_this_month_inr) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-500">{{ s.sahodayas_included }}/{{ s.sahodayas_total }}</td>
                    </tr>
                </SahodayaDataTable>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import { formatDateTime } from '@/support/calendarDates.js';

defineProps({
    snapshots: { type: Array, default: () => [] },
    revenueByMonth: { type: Array, default: () => [] },
    subscriptionStatusBreakdown: { type: Object, default: () => ({}) },
});

const statusOrder = ['active', 'grace', 'readonly', 'suspended'];
const statusLabels = { active: 'Active', grace: 'Grace period', readonly: 'Read-only', suspended: 'Suspended' };
const statusColors = {
    active: 'text-emerald-700', grace: 'text-amber-600',
    readonly: 'text-slate-500', suspended: 'text-red-600',
};

const snapshotColumns = [
    { key: 'computed_at', label: 'Computed at' },
    { key: 'total_students', label: 'Students', align: 'right' },
    { key: 'total_teachers', label: 'Teachers', align: 'right' },
    { key: 'revenue_this_month_inr', label: 'Revenue', align: 'right' },
    { key: 'coverage', label: 'Sahodayas', align: 'right' },
];

function formatAmount(v) {
    return Number(v ?? 0).toLocaleString('en-IN');
}
</script>
