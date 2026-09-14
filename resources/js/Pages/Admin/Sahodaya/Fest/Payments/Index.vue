<template>
    <SahodayaAdminLayout title="Fest payments" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Fest payments queue" eyebrow="Fest programs"
                    description="Approve school batch fee proofs across Kalotsav, Sports, Kids Fest, and Teacher Fest." />

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="tab in statusTabs" :key="tab.key"
                  :href="paymentsHref({ status: tab.key })"
                  :class="activeStatus === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ tab.label }} ({{ statusCounts[tab.key] ?? 0 }})
            </Link>
        </div>

        <div class="flex flex-wrap gap-2 mb-6">
            <Link :href="paymentsHref({ program: null })"
                  :class="!programFilter ? 'subnav-link subnav-link--active' : 'subnav-link'">
                All programs
            </Link>
            <Link v-for="opt in programOptions" :key="opt.slug"
                  :href="paymentsHref({ program: opt.slug })"
                  :class="programFilter === opt.slug ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ opt.label }}
            </Link>
        </div>

        <div class="card card--flush overflow-hidden">
            <EmptyState v-if="!fees.data?.length" title="No payments in this queue"
                        :description="activeStatus === 'pending' ? 'All caught up — no fest fees awaiting approval.' : 'No records for this filter.'" icon="💳" class="py-10" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Event</th>
                            <th>Program</th>
                            <th>School</th>
                            <th>Amount</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(fee, idx) in fees.data" :key="fee.id">
                        <tr>
                            <td>{{ idx + 1 }}</td>
                            <td>
                                <a v-if="fee.event_fees_url" :href="fee.event_fees_url" class="link-brand font-medium">{{ fee.event_title }}</a>
                                <span v-else class="font-medium">{{ fee.event_title }}</span>
                                <p v-if="fee.level_round" class="text-[10px] text-slate-500 capitalize">{{ fee.level_round }}</p>
                                <p v-if="fee.billing_level" class="text-[10px] font-semibold text-indigo-700">{{ fee.billing_level }}</p>
                            </td>
                            <td class="text-xs">{{ fee.program_label }}</td>
                            <td>{{ (fee.school_name || '').toUpperCase() }}</td>
                            <td class="font-semibold">
                                ₹{{ fee.total_due }}
                                <p v-if="fee.pending_count > 1" class="text-[10px] font-semibold text-amber-700 mt-0.5">
                                    {{ fee.pending_count }} proofs pending review (₹{{ fee.pending_total }})
                                </p>
                            </td>
                            <td class="text-xs whitespace-nowrap">{{ formatDateTime(fee.updated_at) }}</td>
                            <td class="text-xs whitespace-nowrap text-right space-x-2">
                                <a v-if="fee.fee_receipt?.proof_url" :href="fee.fee_receipt.proof_url" target="_blank" rel="noopener" class="link-brand">Proof</a>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="approve(fee.id)" class="text-green-700 font-semibold">Approve</button>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="reject(fee.id)" class="text-red-600 font-semibold">Reject</button>
                                <span v-else-if="fee.status === 'approved'" class="text-green-700 font-semibold">Approved</span>
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
                                            <span v-if="r.transaction_ref" class="text-slate-400 ml-2">{{ r.transaction_ref }}</span>
                                            <span v-if="r.uploaded_at" class="text-slate-400 ml-2">({{ r.uploaded_at }})</span>
                                            <span v-if="r.reviewed_by" class="text-slate-400 ml-2">— reviewed by {{ r.reviewed_by }}</span>
                                            <div v-if="r.rejection_reason" class="text-red-600 mt-0.5 font-medium">Rejected: {{ r.rejection_reason }}</div>
                                        </div>
                                        <a v-if="r.proof_url" :href="r.proof_url" target="_blank" rel="noopener" class="text-slate-600 font-semibold hover:underline">Proof ↗</a>
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
import { formatDateTime } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    fees: Object,
    activeStatus: { type: String, default: 'pending' },
    statusCounts: { type: Object, default: () => ({}) },
    programFilter: { type: String, default: null },
    programOptions: { type: Array, default: () => [] },
});

const { confirm, prompt } = useConfirm();

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
    }[status] ?? 'bg-slate-100 text-slate-600';
}

const statusTabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'all', label: 'All' },
];

function paymentsHref(overrides = {}) {
    const params = new URLSearchParams();
    const status = overrides.status ?? props.activeStatus ?? 'pending';
    params.set('status', status);
    const program = overrides.program !== undefined ? overrides.program : props.programFilter;
    if (program) {
        params.set('program', program);
    }
    return `/sahodaya-admin/${props.sahodaya.id}/fest/payments?${params.toString()}`;
}

async function approve(schoolEventFeeId) {
    if (!(await confirm({ message: 'Approve this school event fee and post it to the event income account?', destructive: false }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/fest/payments/${schoolEventFeeId}/approve`, {}, { preserveScroll: true });
}

async function reject(schoolEventFeeId) {
    const reason = await prompt({ message: 'Rejection reason (optional):', inputMultiline: true, inputRequired: false });
    if (reason === null) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/fest/payments/${schoolEventFeeId}/reject`, {
        rejection_reason: reason || null,
    }, { preserveScroll: true });
}
</script>
