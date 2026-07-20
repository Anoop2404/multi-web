<template>
    <SahodayaEventsLayout :title="`${event.title} — Event Fees`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Executive Header with Actions -->
        <PageHeader :title="`${event.title} — Event Fees`" eyebrow="Event Fee Ledger & Submissions"
                    description="Review school fee submissions, payment proofs, and approval status.">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance`" class="btn-secondary text-xs">
                        School Invoices →
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/ledger`" class="btn-primary text-xs">
                        Payment Ledger →
                    </Link>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees/export`" class="btn-secondary text-xs">
                        Export CSV ↓
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Header Navigation Bar -->
        <SportsSetupSubNav v-if="event.event_type === 'sports'"
                           :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="fees" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="fees" class="mb-4" />

        <!-- Guidance Banner Card -->
        <div class="mb-5 rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-sm space-y-1.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-bold text-indigo-900 flex items-center gap-1.5 text-sm">
                    <span>💳</span> Fest Event Fees Ledger
                </p>
                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 font-bold text-indigo-800 text-[11px] border border-indigo-200">
                    {{ levelLabel }}
                </span>
            </div>
            <p class="text-indigo-900/80 leading-relaxed">
                Participation and item charges for this event. Approved fee payments post directly to the event ledger head, separate from Sahodaya annual membership.
                <template v-if="summary.fee_model === 'item_catalog'"> Billing model: <strong>Item catalog billing</strong> (age group / category / per-item rates).</template>
                <template v-else-if="summary.fee_model === 'cksc_tiered'"> Billing model: <strong>Tiered per-item participation fees</strong>.</template>
                <template v-else-if="summary.fee_model === 'sports_composite'"> Billing model: <strong>Sports composite billing</strong> (school reg + per-athlete + team fees).</template>
                <template v-else-if="summary.fee_model === 'none'"> Billing model: <strong>No event fee configured</strong>.</template>
            </p>
        </div>

        <!-- 4 KPI Metric Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
            <div class="card !p-4 border border-slate-200/80">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Due</p>
                <p class="text-2xl font-black text-slate-900 mt-1 tabular-nums">₹{{ Number(summary.total_due).toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Across all registered schools</p>
            </div>

            <div class="card !p-4 border border-emerald-200/80 bg-emerald-50/30">
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Collected</p>
                <p class="text-2xl font-black text-emerald-600 mt-1 tabular-nums">₹{{ Number(summary.total_paid).toLocaleString('en-IN') }}</p>
                <p class="text-[11px] text-emerald-700/80 mt-0.5">Approved &amp; settled</p>
            </div>

            <div class="card !p-4 border border-amber-200/80 bg-amber-50/30">
                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Not Uploaded</p>
                <p class="text-2xl font-black text-amber-600 mt-1 tabular-nums">{{ summary.pending }}</p>
                <p class="text-[11px] text-amber-700/80 mt-0.5">Schools awaiting payment proof</p>
            </div>

            <div class="card !p-4 border border-indigo-200/80 bg-indigo-50/30">
                <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-700">Awaiting Approval</p>
                <p class="text-2xl font-black text-indigo-600 mt-1 tabular-nums">{{ summary.awaiting }}</p>
                <p class="text-[11px] text-indigo-700/80 mt-0.5">Payment proofs requiring review</p>
            </div>
        </div>

        <!-- Filter Chips Bar & Search Toolbar -->
        <div class="card !p-4 space-y-3.5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Status Filter Chips -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1">Filter</span>
                    <button v-for="opt in statusFilterOptions" :key="opt.value" type="button" @click="statusFilter = opt.value"
                            :class="statusFilter === opt.value
                                ? 'bg-slate-900 text-white font-bold shadow-sm'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-full transition whitespace-nowrap">
                        {{ opt.label }} <span class="opacity-75 tabular-nums">({{ opt.count }})</span>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="flex items-center gap-2 flex-1 min-w-[14rem] max-w-xs ml-auto">
                    <input v-model="search" type="search" placeholder="Search school name…"
                           class="field text-xs !py-1.5 flex-1" autocomplete="off">
                    <span class="text-xs text-slate-500 whitespace-nowrap tabular-nums shrink-0 font-medium">
                        {{ filteredRows.length }} of {{ rows.length }} schools
                    </span>
                </div>
            </div>

            <!-- Schools Table -->
            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="p-3.5 w-12 text-center">#</th>
                            <th class="p-3.5">School</th>
                            <th class="p-3.5">Items</th>
                            <th class="p-3.5">Breakdown</th>
                            <th class="p-3.5">Total Due</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in filteredRows" :key="row.id" class="hover:bg-slate-50/70 transition align-top">
                            <td class="p-3.5 text-slate-400 text-center font-mono font-medium">{{ idx + 1 }}</td>
                            <td class="p-3.5 font-bold text-slate-900 max-w-[15rem]">
                                {{ (row.school || '').toUpperCase() }}
                            </td>
                            <td class="p-3.5">
                                <template v-if="event.event_type === 'sports' && row.sports_participation">
                                    <div class="font-bold text-slate-800">
                                        <span v-if="row.sports_participation.team_count > 0">
                                            {{ row.sports_participation.team_count }} team{{ row.sports_participation.team_count === 1 ? '' : 's' }} ({{ row.sports_participation.team_students_count }} stud.)
                                        </span>
                                        <span v-if="row.sports_participation.team_count > 0 && row.sports_participation.indiv_count > 0"> + </span>
                                        <span v-if="row.sports_participation.indiv_count > 0">
                                            {{ row.sports_participation.indiv_count }} indiv. item{{ row.sports_participation.indiv_count === 1 ? '' : 's' }}
                                        </span>
                                        <span v-if="row.sports_participation.team_count === 0 && row.sports_participation.indiv_count === 0" class="text-slate-400 font-normal">
                                            No items registered
                                        </span>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="font-bold text-slate-800">{{ row.participation_item_count }} item(s)</span>
                                </template>
                                <ul v-if="row.items?.length" class="mt-1 text-[11px] text-slate-500 max-h-20 overflow-y-auto space-y-0.5">
                                    <li v-for="(title, i) in row.items" :key="i">• {{ title }}</li>
                                </ul>
                            </td>
                            <td class="p-3.5 space-y-1 max-w-[16rem]">
                                <div v-for="(b, bIdx) in row.breakdown?.items" :key="bIdx" class="flex justify-between gap-3 border-b border-slate-100/80 pb-0.5 last:border-0 last:pb-0">
                                    <span class="text-slate-600 truncate">{{ b.label }}</span>
                                    <span class="font-bold text-slate-900 shrink-0 tabular-nums">₹{{ b.amount }}</span>
                                </div>
                                <div v-if="!row.breakdown?.items?.length" class="text-slate-400">—</div>
                            </td>
                            <td class="p-3.5 font-black text-slate-900 text-sm tabular-nums">
                                ₹{{ Number(row.total_due).toLocaleString('en-IN') }}
                            </td>
                            <td class="p-3.5">
                                <span v-if="isNoFeeDue(row)" class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    No fee due
                                </span>
                                <span v-else-if="row.status === 'approved'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <span>✓</span> Approved
                                </span>
                                <span v-else-if="row.status === 'proof_uploaded'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 animate-pulse">
                                    <span>⏳</span> Awaiting approval
                                </span>
                                <span v-else-if="row.status === 'partial'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-100 text-sky-800 border border-sky-200"
                                      title="Amount paid is less than the current total due — try Refresh first, then Force approve if the receipt genuinely covers it.">
                                    <span>◐</span> Partial
                                </span>
                                <span v-else-if="row.status === 'rejected'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200"
                                      :title="row.fee_receipt?.rejection_reason">
                                    <span>✕</span> Rejected
                                </span>
                                <span v-else class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    Pending proof
                                </span>
                                <p v-if="row.status === 'partial'" class="text-[10px] font-medium text-sky-700 mt-1">
                                    ₹{{ Number(row.amount_paid ?? 0).toLocaleString('en-IN') }} paid of ₹{{ Number(row.total_due).toLocaleString('en-IN') }}
                                </p>

                                <p v-if="row.status === 'rejected' && row.fee_receipt?.rejection_reason"
                                   class="text-[10px] font-medium text-rose-600 mt-1 max-w-[12rem]">
                                    Reason: {{ row.fee_receipt.rejection_reason }}
                                </p>
                            </td>
                            <td class="p-3.5 text-right space-y-1.5">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="!isNoFeeDue(row)" type="button" @click="recalculateFee(row.id)"
                                            title="Recalculate fee from current registrations"
                                            class="btn-secondary !py-1 !px-2 text-[11px] inline-flex items-center gap-1">
                                        <span>🔄 Refresh</span>
                                    </button>
                                    <a v-if="row.fee_receipt?.file_path"
                                       :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/school-fees/${row.id}/proof`"
                                       target="_blank" rel="noopener"
                                       class="btn-secondary !py-1 !px-2 text-[11px] text-indigo-700 font-bold">
                                        View proof ↗
                                    </a>
                                </div>

                                <div v-if="row.status === 'proof_uploaded'" class="flex items-center justify-end gap-1.5 pt-1">
                                    <button type="button" @click="approve(row.id)" class="btn-primary !bg-emerald-600 hover:!bg-emerald-500 text-[11px] !py-1 !px-2.5">
                                        Approve ✓
                                    </button>
                                    <button type="button" @click="reject(row.id)" class="btn-secondary text-[11px] !py-1 !px-2.5 !text-rose-700 hover:!bg-rose-50">
                                        Reject
                                    </button>
                                </div>

                                <div v-if="row.status === 'partial'" class="flex items-center justify-end pt-1">
                                    <button type="button" @click="forceApprove(row)"
                                            title="Waives the gap between total due and amount paid, then approves — use only when the receipt genuinely covers the school's current items."
                                            class="btn-secondary text-[11px] !py-1 !px-2.5 !text-sky-700 hover:!bg-sky-50">
                                        Force approve (waive ₹{{ partialShortfall(row) }})
                                    </button>
                                </div>

                                <span v-if="row.fee_receipt?.receipt_number" class="text-[11px] font-mono font-bold text-emerald-700 block">
                                    #{{ row.fee_receipt.receipt_number }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="7" class="p-12 text-center text-slate-400">
                                <p class="text-sm font-medium">{{ rows.length ? 'No schools match this filter/search.' : 'No school event fees yet.' }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, summary: Object, levelLabel: String, feeSchedule: Object,
    activityLogs: { type: Array, default: () => [] },
});

const search = ref('');

function isNoFeeDue(row) {
    return Number(row.total_due) === 0 && row.status === 'approved';
}

function hasRegisteredItems(row) {
    if (props.event.event_type === 'sports' && row.sports_participation) {
        return (row.sports_participation.team_count > 0 || row.sports_participation.indiv_count > 0);
    }
    return (row.participation_item_count > 0) || (row.items && row.items.length > 0) || Number(row.total_due) > 0;
}

function isUnpaidPending(row) {
    return row.status === 'pending' && !isNoFeeDue(row);
}

const statusFilter = ref('all');
const statusFilterOptions = computed(() => {
    const rows = props.rows;
    const activeRows = rows.filter(hasRegisteredItems);
    return [
        { value: 'all', label: 'Registered schools', count: activeRows.filter(r => !isUnpaidPending(r)).length },
        { value: 'proof_uploaded', label: 'Awaiting approval', count: activeRows.filter(r => r.status === 'proof_uploaded').length },
        { value: 'partial', label: 'Partial', count: activeRows.filter(r => r.status === 'partial').length },
        { value: 'approved', label: 'Approved', count: activeRows.filter(r => r.status === 'approved' && !isNoFeeDue(r)).length },
        { value: 'rejected', label: 'Rejected', count: activeRows.filter(r => r.status === 'rejected').length },
        { value: 'pending', label: 'Not uploaded yet', count: activeRows.filter(isUnpaidPending).length },
        { value: 'everything', label: 'All schools (incl. 0 items)', count: rows.length },
    ];
});

const filteredRows = computed(() => {
    let rows = props.rows;

    if (statusFilter.value === 'all') {
        rows = rows.filter(r => hasRegisteredItems(r) && !isUnpaidPending(r));
    } else if (statusFilter.value === 'pending') {
        rows = rows.filter(r => hasRegisteredItems(r) && isUnpaidPending(r));
    } else if (statusFilter.value === 'approved') {
        rows = rows.filter(r => hasRegisteredItems(r) && r.status === 'approved' && !isNoFeeDue(r));
    } else if (statusFilter.value !== 'everything') {
        rows = rows.filter(r => hasRegisteredItems(r) && r.status === statusFilter.value);
    }

    const q = search.value.trim().toLowerCase();
    if (q) {
        rows = rows.filter(row => (row.school ?? '').toLowerCase().includes(q));
    }

    return rows;
});

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

function partialShortfall(row) {
    return Number(Math.max(0, Number(row.total_due) - Number(row.amount_paid ?? 0))).toLocaleString('en-IN');
}

function forceApprove(row) {
    const reason = prompt(
        `This waives ₹${partialShortfall(row)} (the gap between total due and amount paid) and approves the school's registrations.\n`
        + `Only do this if the uploaded receipt genuinely covers their current items. Reason (required):`
    );
    if (!reason) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/school-fees/${row.id}/force-approve`, {
        reason,
    }, { preserveScroll: true });
}
</script>
