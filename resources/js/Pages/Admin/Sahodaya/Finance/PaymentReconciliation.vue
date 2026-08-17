<template>
    <SahodayaAdminLayout title="Payment reconciliation" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Payment reconciliation" eyebrow="Finance integrity"
                    description="Review payment exceptions by school and apply controlled, audited corrections." />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card !p-4">
                <p class="metric-label">Exceptions</p>
                <p class="text-2xl font-black text-red-700 mt-1">{{ summary.exceptions }}</p>
            </div>
            <div class="card !p-4">
                <p class="metric-label">Unreconciled excess</p>
                <p class="text-2xl font-black text-red-700 mt-1">₹{{ fmt(summary.unreconciled) }}</p>
            </div>
            <div class="card !p-4">
                <p class="metric-label">Recorded school credit</p>
                <p class="text-2xl font-black text-emerald-700 mt-1">₹{{ fmt(summary.recorded_credit) }}</p>
            </div>
            <div class="card !p-4">
                <p class="metric-label">Receipt ledger issues</p>
                <p class="text-2xl font-black text-amber-700 mt-1">{{ summary.receipt_issues }}</p>
            </div>
        </div>

        <div class="card !p-4 mb-6">
            <div class="grid gap-3 md:grid-cols-3">
                <label class="text-xs font-semibold text-slate-600">
                    Payment type
                    <select v-model="filterForm.type" class="field mt-1">
                        <option value="all">All payment types</option>
                        <option value="fest">Fest / sports</option>
                        <option value="mcq">Talent Search</option>
                        <option value="training">Teacher training</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">
                    Event
                    <select v-model="filterForm.event_id" class="field mt-1">
                        <option value="">All events and programmes</option>
                        <option v-for="event in events" :key="event.id" :value="event.id">{{ event.title }}</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">
                    School
                    <select v-model="filterForm.school_id" class="field mt-1">
                        <option value="">All schools</option>
                        <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                    </select>
                </label>
            </div>
            <div class="mt-3 flex gap-2">
                <button class="btn-primary text-sm" type="button" @click="applyFilters">Apply filters</button>
                <button class="btn-secondary text-sm" type="button" @click="clearFilters">Clear</button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance/payments/credits`" class="btn-secondary text-sm ml-auto">
                    Credits &amp; payouts
                </Link>
            </div>
        </div>

        <section class="card !p-0 overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-200">
                <h2 class="font-bold text-slate-900">Fee balance exceptions</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Recording credit preserves the original approved receipt and posts the excess to Fee Credits Payable.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table min-w-[1100px]">
                    <thead>
                        <tr>
                            <th>School / programme</th>
                            <th class="text-right">Current due</th>
                            <th class="text-right">Approved receipts</th>
                            <th class="text-right">Stored paid</th>
                            <th class="text-right">Excess</th>
                            <th class="text-right">Credit recorded</th>
                            <th>Approved receipts</th>
                            <th>Correction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="`${row.carrier_type}-${row.carrier_id}`">
                            <td>
                                <p class="font-bold text-slate-900">{{ row.school_name }}</p>
                                <p class="text-xs text-slate-500">{{ row.program }}</p>
                                <p class="text-[10px] uppercase text-slate-400 mt-1">{{ typeLabel(row.carrier_type) }} · Fee #{{ row.carrier_id }}</p>
                                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance/payments?school_id=${row.school_id}&show_all=1`"
                                      class="text-xs link-brand mt-1 inline-block">Open school payment history →</Link>
                            </td>
                            <td class="text-right font-semibold tabular-nums">₹{{ fmt(row.total_due) }}</td>
                            <td class="text-right font-semibold tabular-nums">₹{{ fmt(row.approved_receipts) }}</td>
                            <td class="text-right tabular-nums"
                                :class="row.paid_drift > 0 ? 'text-red-700 font-bold' : 'text-slate-700'">
                                ₹{{ fmt(row.stored_amount_paid) }}
                            </td>
                            <td class="text-right font-bold text-red-700 tabular-nums">₹{{ fmt(row.overpayment) }}</td>
                            <td class="text-right font-semibold text-emerald-700 tabular-nums">₹{{ fmt(row.recorded_credit) }}</td>
                            <td>
                                <ul class="space-y-1 text-xs">
                                    <li v-for="receipt in row.receipts" :key="receipt.id">
                                        <span class="font-semibold">{{ receipt.number || `#${receipt.id}` }}</span>
                                        · ₹{{ fmt(receipt.amount) }}
                                        <span v-if="receipt.payment_date" class="text-slate-400">· {{ receipt.payment_date }}</span>
                                    </li>
                                </ul>
                            </td>
                            <td>
                                <div class="flex flex-col items-start gap-2">
                                    <button v-if="row.unreconciled > 0" type="button"
                                            class="btn-primary text-xs"
                                            :disabled="busyKey === rowKey(row)"
                                            @click="recordCredit(row)">
                                        Record ₹{{ fmt(row.unreconciled) }} credit
                                    </button>
                                    <span v-else class="status-badge status-badge--approved">Credit reconciled</span>
                                    <button v-if="row.paid_drift > 0" type="button"
                                            class="btn-secondary text-xs"
                                            :disabled="busyKey === rowKey(row)"
                                            @click="refreshPaid(row)">
                                        Recalculate paid total
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="8" class="p-10 text-center text-sm text-slate-500">
                                No fee balance exceptions match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card !p-0 overflow-hidden">
            <div class="p-4 border-b border-slate-200">
                <h2 class="font-bold text-slate-900">Approved receipt ledger exceptions</h2>
                <p class="text-xs text-slate-500 mt-1">Reposting rebuilds the double-entry journal from the approved receipt amount.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Receipt</th>
                            <th>Issue</th>
                            <th class="text-right">Receipt amount</th>
                            <th class="text-right">Ledger amount</th>
                            <th>Correction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in receiptIssues" :key="issue.receipt_id">
                            <td class="font-semibold">{{ issue.school_name }}</td>
                            <td>{{ issue.receipt_number || `#${issue.receipt_id}` }}</td>
                            <td class="text-red-700 font-semibold">{{ issue.issue }}</td>
                            <td class="text-right">₹{{ fmt(issue.receipt_amount) }}</td>
                            <td class="text-right">₹{{ fmt(issue.ledger_amount) }}</td>
                            <td>
                                <button type="button" class="btn-secondary text-xs"
                                        :disabled="busyKey === `receipt-${issue.receipt_id}`"
                                        @click="repostReceipt(issue)">
                                    Rebuild ledger journal
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!receiptIssues.length">
                            <td colspan="6" class="p-8 text-center text-sm text-slate-500">
                                No approved receipt ledger exceptions in the selected results.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    receiptIssues: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    type: props.filters.type || 'all',
    event_id: props.filters.event_id || '',
    school_id: props.filters.school_id || '',
});
const busyKey = ref('');
const { confirm } = useConfirm();
const base = `/sahodaya-admin/${props.sahodaya.id}/finance/payment-reconciliation`;

function fmt(value) {
    return Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function typeLabel(type) {
    return { fest: 'Fest / sports', mcq: 'Talent Search', training: 'Teacher training' }[type] || type;
}

function rowKey(row) {
    return `${row.carrier_type}-${row.carrier_id}`;
}

function applyFilters() {
    router.get(base, filterForm, { preserveState: true, replace: true });
}

function clearFilters() {
    filterForm.type = 'all';
    filterForm.event_id = '';
    filterForm.school_id = '';
    applyFilters();
}

async function recordCredit(row) {
    const message = `Record ₹${fmt(row.unreconciled)} as credit owed to ${row.school_name}? This posts an audited ledger entry and does not change the original receipt.`;
    if (!(await confirm({ message, destructive: false }))) return;

    busyKey.value = rowKey(row);
    router.post(`${base}/record-credit`, {
        carrier_type: row.carrier_type,
        carrier_id: row.carrier_id,
        reason: `Historical overpayment reconciled for ${row.program}`,
    }, {
        preserveScroll: true,
        onFinish: () => { busyKey.value = ''; },
    });
}

async function refreshPaid(row) {
    if (!(await confirm({ message: `Recalculate the stored paid total for ${row.school_name} from approved receipts?`, destructive: false }))) return;
    busyKey.value = rowKey(row);
    router.post(`${base}/refresh-paid-state`, {
        carrier_type: row.carrier_type,
        carrier_id: row.carrier_id,
    }, {
        preserveScroll: true,
        onFinish: () => { busyKey.value = ''; },
    });
}

async function repostReceipt(issue) {
    if (!(await confirm({ message: `Rebuild the ledger journal for receipt ${issue.receipt_number || `#${issue.receipt_id}`}?`, destructive: false }))) return;
    busyKey.value = `receipt-${issue.receipt_id}`;
    router.post(`${base}/receipts/${issue.receipt_id}/repost`, {}, {
        preserveScroll: true,
        onFinish: () => { busyKey.value = ''; },
    });
}
</script>
