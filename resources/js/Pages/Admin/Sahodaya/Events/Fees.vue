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

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">School</th>
                        <th class="p-3">Items</th>
                        <th class="p-3">Breakdown</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-t align-top">
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
                            <span :class="statusClass(row.status)" class="text-xs font-semibold px-2 py-0.5 rounded">
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="p-3 text-right text-xs space-y-1">
                            <button @click="recalculateFee(row.id)" class="text-slate-500 hover:text-slate-900 font-semibold block mb-1">Recalculate</button>
                            <template v-if="row.status === 'proof_uploaded'">
                                <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/school-fees/${row.id}/proof`"
                                   target="_blank" rel="noopener"
                                   class="block text-indigo-600 font-semibold">View proof ↗</a>
                                <button @click="approve(row.id)" class="text-green-600 font-semibold block">Approve</button>
                                <button @click="reject(row.id)" class="text-red-600 font-semibold block">Reject</button>
                            </template>
                            <span v-if="row.fee_receipt?.receipt_number" class="text-green-700 block">
                                #{{ row.fee_receipt.receipt_number }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No school fees yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, summary: Object, levelLabel: String, feeSchedule: Object,
    activityLogs: { type: Array, default: () => [] },
});

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
