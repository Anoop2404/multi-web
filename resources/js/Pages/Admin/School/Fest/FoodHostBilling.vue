<template>
    <SchoolAdminLayout :title="`${event.title} — Food Billing (Host)`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Billing`" eyebrow="Programs"
                    description="Your school is the designated payee for this event's food orders — manage every participating school's bill here." />

        <div class="flex justify-end mb-3">
            <a :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing/export`"
               class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Export CSV</a>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-6 max-w-lg">
            <div class="card text-center">
                <p class="text-xl font-bold">₹{{ summary.total.toFixed(2) }}</p>
                <p class="text-xs text-gray-500">Total billed</p>
            </div>
            <div class="card text-center">
                <p class="text-xl font-bold text-green-700">₹{{ summary.paid.toFixed(2) }}</p>
                <p class="text-xs text-gray-500">Paid</p>
            </div>
            <div class="card text-center">
                <p class="text-xl font-bold" :class="summary.balance > 0 ? 'text-amber-700' : 'text-gray-700'">₹{{ summary.balance.toFixed(2) }}</p>
                <p class="text-xs text-gray-500">Balance due</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 items-center mb-4">
            <input v-model="search" type="search" class="field flex-1 min-w-[12rem] max-w-sm text-sm"
                   placeholder="Search by school…" autocomplete="off">
            <select v-model="statusFilter" class="field text-sm w-auto">
                <option value="">All statuses</option>
                <option value="open">Open</option>
                <option value="settled">Settled</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" v-model="onlyBalanceDue"> Only with balance due
            </label>
            <button v-if="search || statusFilter || onlyBalanceDue" type="button" class="text-xs text-indigo-600 font-semibold" @click="clearFilters">Clear filters</button>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">School</th>
                        <th class="p-3">Items</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Paid</th>
                        <th class="p-3">Balance</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in filteredBills" :key="b.id" class="border-t">
                        <td class="p-3">{{ b.school_name }}</td>
                        <td class="p-3">{{ b.items_count }}</td>
                        <td class="p-3">₹{{ b.amount_total.toFixed(2) }}</td>
                        <td class="p-3">₹{{ b.amount_paid.toFixed(2) }}</td>
                        <td class="p-3" :class="b.balance_due > 0 ? 'text-amber-700 font-semibold' : ''">₹{{ b.balance_due.toFixed(2) }}</td>
                        <td class="p-3">
                            <span class="text-xs px-2 py-0.5 rounded" :class="b.status === 'settled' ? 'bg-green-100 text-green-700' : 'bg-gray-100'">{{ b.status }}</span>
                        </td>
                        <td class="p-3 text-right">
                            <Link :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing/${b.id}`" class="text-xs font-semibold text-indigo-600">View</Link>
                        </td>
                    </tr>
                    <tr v-if="!bills.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No bills yet — they appear once a school orders from the food menu.</td>
                    </tr>
                    <tr v-else-if="!filteredBills.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No bills match your filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    event: Object,
    bills: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, paid: 0, balance: 0 }) },
});

const school = computed(() => usePage().props.school);

const search = ref('');
const statusFilter = ref('');
const onlyBalanceDue = ref(false);

const filteredBills = computed(() => props.bills.filter((b) => {
    if (search.value.trim() && !b.school_name.toLowerCase().includes(search.value.trim().toLowerCase())) return false;
    if (statusFilter.value && b.status !== statusFilter.value) return false;
    if (onlyBalanceDue.value && b.balance_due <= 0) return false;
    return true;
}));

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
    onlyBalanceDue.value = false;
}
</script>
