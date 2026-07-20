<template>
    <SahodayaEventsLayout :title="`${event.title} — Event Fees`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Event Fees`" eyebrow="Finance"
                    description="Review school event fee submissions and approval status." />
        <p class="text-sm text-gray-600 mb-4 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
            <strong>Fest event fees</strong> — participation and item charges for this event only.
            <strong>Annual Sahodaya school membership</strong> is paid separately under Membership → Membership fees — it is not included here unless you explicitly enabled an optional add-on in Event settings → Fees.
            <span class="block mt-2">{{ levelLabel }} —
            <template v-if="summary.fee_model === 'item_catalog'">Item catalog billing (age group / category / per-item rates).</template>
            <template v-else-if="summary.fee_model === 'cksc_tiered'">Tiered per-item participation fees.</template>
            <template v-else-if="summary.fee_model === 'sports_composite'">Sports composite billing (school reg + per-athlete + team fees).</template>
            <template v-else-if="summary.fee_model === 'none'">No event fee configured.</template>
            <template v-else>Custom fee model for this event.</template>
            Approved fees post to a <strong>dedicated ledger head per event</strong>, separate from MEMBERSHIP.</span>
        </p>
        <div class="flex flex-wrap gap-2 mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance`" class="btn-secondary">
                School invoices →
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/ledger`" class="btn-primary">
                Payment ledger →
            </Link>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/export`" class="btn-secondary text-sm">
                Export CSV ↓
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="card text-center">
                <p class="text-2xl font-bold">₹{{ summary.total_due }}</p>
                <p class="text-xs text-gray-500">Total due</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">₹{{ summary.total_paid }}</p>
                <p class="text-xs text-gray-500">Collected</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-amber-600">{{ summary.pending }}</p>
                <p class="text-xs text-gray-500">Not uploaded</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-indigo-600">{{ summary.awaiting }}</p>
                <p class="text-xs text-gray-500">Awaiting approval</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 mb-3">
            <input v-model="search" type="search" placeholder="Search school…"
                   class="field text-sm w-full max-w-xs">
            <span class="text-xs text-gray-500 whitespace-nowrap">{{ filteredRows.length }} of {{ rows.length }} schools</span>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Sl. No.</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Items</th>
                        <th class="p-3">Breakdown</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in filteredRows" :key="row.id" class="border-t align-top">
                        <td class="p-3 text-gray-400">{{ idx + 1 }}</td>
                        <td class="p-3 font-medium">{{ row.school }}</td>
                        <td class="p-3 text-xs">
                            <template v-if="event.event_type === 'sports' && row.sports_participation">
                                <div class="font-semibold text-slate-800">
                                    <span v-if="row.sports_participation.team_count > 0">
                                        {{ row.sports_participation.team_count }} team{{ row.sports_participation.team_count === 1 ? '' : 's' }} ({{ row.sports_participation.team_students_count }} stud.)
                                    </span>
                                    <span v-if="row.sports_participation.team_count > 0 && row.sports_participation.indiv_count > 0"> + </span>
                                    <span v-if="row.sports_participation.indiv_count > 0">
                                        {{ row.sports_participation.indiv_count }} indiv. item{{ row.sports_participation.indiv_count === 1 ? '' : 's' }}
                                    </span>
                                    <span v-if="row.sports_participation.team_count === 0 && row.sports_participation.indiv_count === 0" class="text-slate-400">
                                        No items registered
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                {{ row.participation_item_count }} item(s)
                            </template>
                            <ul v-if="row.items?.length" class="mt-1 text-gray-500 max-h-24 overflow-y-auto">
                                <li v-for="(title, i) in row.items" :key="i">{{ title }}</li>
                            </ul>
                        </td>
                        <td class="p-3 text-xs space-y-1">
                            <div v-for="(b, idx) in row.breakdown?.items" :key="idx" class="flex justify-between gap-3 border-b border-slate-100/50 pb-0.5 last:border-0 last:pb-0 max-w-[16rem]">
                                <span class="text-slate-600">{{ b.label }}</span>
                                <span class="font-semibold text-slate-900 shrink-0">₹{{ b.amount }}</span>
                            </div>
                            <div v-if="!row.breakdown?.items?.length" class="text-slate-400">—</div>
                        </td>
                        <td class="p-3 font-semibold">₹{{ row.total_due }}</td>
                        <td class="p-3">
                            <span v-if="isNoFeeDue(row)" class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-50 text-gray-500">
                                No fee due
                            </span>
                            <span v-else :class="statusClass(row.status)" class="text-xs font-semibold px-2 py-0.5 rounded"
                                  :title="row.status === 'rejected' ? row.fee_receipt?.rejection_reason : null">
                                {{ row.status }}
                            </span>
                            <p v-if="row.status === 'rejected' && row.fee_receipt?.rejection_reason"
                               class="text-[11px] text-red-600 mt-1 max-w-[14rem]">
                                {{ row.fee_receipt.rejection_reason }}
                            </p>
                        </td>
                        <td class="p-3 text-right text-xs space-y-1">
                            <button v-if="!isNoFeeDue(row)" @click="recalculateFee(row.id)"
                                    title="Refresh this school's fee from current registrations/settings"
                                    class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-900 font-semibold mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                                    <path fill-rule="evenodd" d="M15.312 5.312a5.5 5.5 0 0 0-9.201 2.466.75.75 0 0 1-1.453-.373 7 7 0 0 1 11.712-3.138l1.005 1.005V3.5a.75.75 0 0 1 1.5 0V7a.75.75 0 0 1-.75.75h-3.5a.75.75 0 0 1 0-1.5h1.938l-.951-.938ZM4.688 14.688a5.5 5.5 0 0 0 9.201-2.466.75.75 0 1 1 1.453.373 7 7 0 0 1-11.712 3.138l-1.005-1.005V16.5a.75.75 0 0 1-1.5 0V13a.75.75 0 0 1 .75-.75h3.5a.75.75 0 0 1 0 1.5H3.437l.951.938Z" clip-rule="evenodd" />
                                </svg>
                                Refresh
                            </button>
                            <a v-if="row.fee_receipt?.file_path"
                               :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/school-fees/${row.id}/proof`"
                               target="_blank" rel="noopener"
                               class="block text-indigo-600 font-semibold">View proof ↗</a>
                            <template v-if="row.status === 'proof_uploaded'">
                                <button @click="approve(row.id)" class="text-green-600 font-semibold block">Approve</button>
                                <button @click="reject(row.id)" class="text-red-600 font-semibold block">Reject</button>
                            </template>
                            <span v-if="row.fee_receipt?.receipt_number" class="text-green-700 block">
                                #{{ row.fee_receipt.receipt_number }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!filteredRows.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">
                            {{ rows.length ? 'No schools match your search.' : 'No school fees yet.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, summary: Object, levelLabel: String, feeSchedule: Object,
    activityLogs: { type: Array, default: () => [] },
});

const search = ref('');
const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.rows;
    return props.rows.filter(row => (row.school ?? '').toLowerCase().includes(q));
});

// "No items registered, ₹0 due" rows were showing a green "approved" badge, which
// reads as if a payment was reviewed and cleared — there was never anything to
// approve. Show a neutral "No fee due" label instead, without changing the
// underlying stored status (isFullyPaid()/dashboard tiles still rely on it).
function isNoFeeDue(row) {
    return Number(row.total_due) === 0 && row.status === 'approved';
}

function statusClass(status) {
    return {
        approved: 'bg-green-50 text-green-700',
        proof_uploaded: 'bg-yellow-50 text-yellow-700',
        rejected: 'bg-red-50 text-red-600',
        pending: 'bg-gray-50 text-gray-600',
    }[status] ?? 'bg-gray-50 text-gray-600';
}

function approve(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${id}/approve`, {}, { preserveScroll: true });
}

function reject(id) {
    const reason = prompt('Rejection reason (optional):');
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${id}/reject`, {
        rejection_reason: reason ?? '',
    }, { preserveScroll: true });
}

function recalculateFee(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${id}/recalculate`, {}, { preserveScroll: true });
}
</script>
