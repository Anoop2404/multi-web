<template>
    <SahodayaAdminLayout title="Talent Search payments" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Talent Search payments queue" eyebrow="Talent Search exams"
                    description="Approve school batch fee proofs across all exams without opening each exam workspace." />

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex flex-wrap gap-2">
                <Link v-for="tab in statusTabs" :key="tab.key"
                      :href="`/sahodaya-admin/${sahodaya.id}/mcq/payments?status=${tab.key}${search ? '&search=' + encodeURIComponent(search) : ''}`"
                      :class="activeStatus === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                    {{ tab.label }} ({{ statusCounts[tab.key] ?? 0 }})
                </Link>
            </div>
            <input v-model="search" type="search" placeholder="Search exam or school…"
                   class="form-input w-full sm:w-64 text-sm" @input="onSearchInput" />
        </div>

        <div class="card card--flush overflow-hidden">
            <EmptyState v-if="!fees.data?.length" title="No payments in this queue"
                        :description="activeStatus === 'pending' ? 'All caught up — no batch fees awaiting approval.' : 'No records for this filter.'" icon="💳" class="py-10" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Exam</th>
                            <th>School</th>
                            <th>Students</th>
                            <th>Amount</th>
                            <th>Uploaded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(fee, idx) in fees.data" :key="fee.id">
                        <tr>
                            <td>{{ idx + 1 }}</td>
                            <td>
                                <Link :href="fee.payments_url" class="link-brand font-medium">{{ fee.exam_title }}</Link>
                                <p v-if="fee.exam_level > 1" class="text-[10px] text-indigo-700">Level {{ fee.exam_level }}</p>
                            </td>
                            <td>{{ (fee.school_name || '').toUpperCase() }}</td>
                            <td>{{ fee.student_count }}</td>
                            <td class="font-semibold">
                                <template v-if="fee.status === 'partial'">
                                    ₹{{ fee.amount_paid }} <span class="text-slate-400 font-normal">of ₹{{ fee.total_due }}</span>
                                </template>
                                <template v-else>₹{{ fee.total_due }}</template>
                            </td>
                            <td class="text-xs whitespace-nowrap">{{ formatDateTime(fee.updated_at) }}</td>
                            <td class="text-xs whitespace-nowrap text-right space-x-2">
                                <a v-if="fee.fee_receipt?.proof_url" :href="fee.fee_receipt.proof_url" target="_blank" rel="noopener" class="link-brand">Proof</a>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="approve(fee.id)" class="text-green-700 font-semibold">Approve</button>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="reject(fee.id)" class="text-red-700 font-semibold">Reject</button>
                                <span v-else-if="fee.fee_receipt?.status === 'rejected'" class="text-red-700" :title="fee.fee_receipt?.rejection_reason">Rejected</span>
                                <span v-else-if="fee.status === 'approved'" class="text-green-700 font-semibold">Approved</span>
                                <span v-else-if="fee.status === 'partial'" class="text-amber-700 font-semibold">Partial</span>
                                <button v-if="fee.receipts_history?.length > 1" type="button"
                                        class="block ml-auto mt-1 text-[11px] text-indigo-600 hover:text-indigo-800 font-semibold"
                                        @click="toggleExpand(fee.id)">
                                    {{ expanded[fee.id] ? 'Hide' : 'Show' }} history ({{ fee.receipts_history.length }})
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expanded[fee.id] && fee.receipts_history">
                            <td colspan="7" class="bg-slate-50">
                                <div class="pl-3 border-l-2 border-slate-200 space-y-2 py-2">
                                    <div v-for="r in fee.receipts_history" :key="r.id"
                                         class="text-xs text-slate-600 flex flex-wrap items-center justify-between gap-2 bg-white p-2 rounded border border-slate-100">
                                        <div>
                                            <span v-if="r.receipt_number" class="font-mono text-indigo-700 mr-2">#{{ r.receipt_number }}</span>
                                            <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded mr-2" :class="statusClass(r.status)">{{ r.status }}</span>
                                            <span class="font-semibold">₹{{ r.amount }}</span>
                                            <span v-if="r.payment_date" class="text-slate-400 ml-2">({{ formatCalendarDate(r.payment_date) }})</span>
                                            <span v-if="r.reviewed_by" class="text-slate-400 ml-2">— reviewed by {{ r.reviewed_by }}</span>
                                            <div v-if="r.rejection_reason" class="text-red-600 mt-0.5 font-medium">Rejected: {{ r.rejection_reason }}</div>
                                            <div v-if="r.reversal_reason" class="text-red-600 mt-0.5 font-medium">Reversed: {{ r.reversal_reason }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a v-if="r.receipt_url" :href="r.receipt_url" target="_blank" rel="noopener" class="text-indigo-600 font-semibold hover:underline">Receipt ↗</a>
                                            <a v-if="r.proof_url" :href="r.proof_url" target="_blank" rel="noopener" class="text-slate-600 font-semibold hover:underline">Proof ↗</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="fees.links?.length > 3" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in fees.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  :class="['px-3 py-1 text-sm rounded', link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { formatDateTime, formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    fees: Object,
    activeStatus: { type: String, default: 'pending' },
    statusCounts: { type: Object, default: () => ({}) },
    search: { type: String, default: '' },
});

const statusTabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'partial', label: 'Partial' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all', label: 'All' },
];

const search = ref(props.search);
let searchTimeout = null;

const expanded = ref({});
function toggleExpand(id) {
    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

function statusClass(status) {
    return {
        approved:   'bg-green-50 text-green-700',
        uploaded:   'bg-amber-50 text-amber-700',
        rejected:   'bg-rose-50 text-rose-700',
        reversed:   'bg-red-100 text-red-800 line-through',
        superseded: 'bg-slate-100 text-slate-500 line-through',
        partial:    'bg-amber-100 text-amber-800',
        pending:    'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(`/sahodaya-admin/${props.sahodaya.id}/mcq/payments`, {
            status: props.activeStatus,
            search: search.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
}

function approve(schoolFeeId) {
    if (!confirm('Approve this batch fee and issue hall tickets for all registered students from this school?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/payments/${schoolFeeId}/approve`, {}, { preserveScroll: true });
}

function reject(schoolFeeId) {
    const reason = prompt('Rejection reason for the school:');
    if (!reason?.trim()) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/payments/${schoolFeeId}/reject`, {
        rejection_reason: reason.trim(),
    }, { preserveScroll: true });
}
</script>
