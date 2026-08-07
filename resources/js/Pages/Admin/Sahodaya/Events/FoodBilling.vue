<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Billing`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Billing`" eyebrow="Operations"
                    description="Per-school food bills." />

        <div class="flex flex-wrap gap-2 items-center mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-menu`" class="text-sm text-indigo-600">← Food Menu</Link>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing/export`"
               class="ml-auto px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Export CSV</a>
        </div>

        <div v-if="event.food_payee_type === 'host_school'" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800 mb-4">
            <strong>{{ hostSchoolName || 'The host school' }}</strong> is the designated payee for this event and manages billing directly from their own dashboard.
            You can still view and record payments here for oversight, but day-to-day billing is expected to happen on their side.
        </div>
        <div v-else class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 mb-4">
            {{ payeeNote }}
        </div>

        <!-- Region-partitioned hub: bills live on each region's own event, this page's
             own totals are empty by construction — show the cross-region rollup instead. -->
        <div v-if="isPartitionedHub" class="rounded-xl border border-gray-200 bg-white p-4 mb-4">
            <h3 class="font-bold text-sm mb-3">Combined across all regions</h3>
            <div class="grid grid-cols-3 gap-3 max-w-lg mb-3">
                <div class="card text-center">
                    <p class="text-xl font-bold">₹{{ regionFoodSummary.billing.total.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">Total billed</p>
                </div>
                <div class="card text-center">
                    <p class="text-xl font-bold text-green-700">₹{{ regionFoodSummary.billing.paid.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">Paid</p>
                </div>
                <div class="card text-center">
                    <p class="text-xl font-bold" :class="regionFoodSummary.billing.balance > 0 ? 'text-amber-700' : 'text-gray-700'">₹{{ regionFoodSummary.billing.balance.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">Balance due</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mb-3">
                Catering headcount: {{ regionFoodSummary.catering_head_count }} ·
                Coupons issued: {{ regionFoodSummary.coupons.issued }} ·
                Redeemed: {{ regionFoodSummary.coupons.redeemed }}
            </p>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-2">Region</th>
                        <th class="p-2">Total</th>
                        <th class="p-2">Paid</th>
                        <th class="p-2">Balance</th>
                        <th class="p-2">Headcount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in regionFoodSummary.by_region" :key="r.region" class="border-t">
                        <td class="p-2">{{ r.region }}</td>
                        <td class="p-2">₹{{ r.total.toFixed(2) }}</td>
                        <td class="p-2">₹{{ r.paid.toFixed(2) }}</td>
                        <td class="p-2">₹{{ r.balance.toFixed(2) }}</td>
                        <td class="p-2">{{ r.head_count }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">To manage an individual region's bills, open that region's own event page.</p>
        </div>

        <div v-else class="grid grid-cols-3 gap-3 mb-4 max-w-lg">
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

        <!-- Open a bill for a school (e.g. before they've ordered, or a walk-in) -->
        <form v-if="!isPartitionedHub" @submit.prevent="openBill" class="flex flex-wrap items-end gap-3 mb-6">
            <FormField label="Open/find bill for school" :error="openForm.errors.school_id">
                <template #default="{ id }">
                    <select :id="id" v-model="openForm.school_id" class="field text-sm">
                        <option value="">— Select school —</option>
                        <option v-for="s in schoolOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </template>
            </FormField>
            <button type="submit" class="btn-secondary text-sm" :disabled="openForm.processing || !openForm.school_id">Open</button>
        </form>

        <template v-if="!isPartitionedHub">
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
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing/${b.id}`" class="text-xs font-semibold text-indigo-600">View</Link>
                        </td>
                    </tr>
                    <tr v-if="!bills.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No bills yet — they're created automatically once a school orders, or open one above.</td>
                    </tr>
                    <tr v-else-if="!filteredBills.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No bills match your filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </template>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, hostSchoolName: { type: String, default: null },
    bills: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, paid: 0, balance: 0 }) },
    schoolOptions: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    isPartitionedHub: { type: Boolean, default: false },
    regionFoodSummary: { type: Object, default: null },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;

const payeeNote = computed(() => (
    props.event.food_payee_type === 'host_school'
        ? `Payments are payable to ${props.hostSchoolName || 'the host school'}, not the Sahodaya.`
        : 'Payments are payable to the Sahodaya.'
));

const openForm = useForm({ school_id: '' });
function openBill() {
    openForm.post(`${base}/food-billing`);
}

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
