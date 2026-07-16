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
                            <th>Event</th>
                            <th>Program</th>
                            <th>School</th>
                            <th>Amount</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fee in fees.data" :key="fee.id">
                            <td>
                                <a v-if="fee.event_fees_url" :href="fee.event_fees_url" class="link-brand font-medium">{{ fee.event_title }}</a>
                                <span v-else class="font-medium">{{ fee.event_title }}</span>
                                <p v-if="fee.level_round" class="text-[10px] text-slate-500 capitalize">{{ fee.level_round }}</p>
                            </td>
                            <td class="text-xs">{{ fee.program_label }}</td>
                            <td>{{ fee.school_name }}</td>
                            <td class="font-semibold">₹{{ fee.total_due }}</td>
                            <td class="text-xs whitespace-nowrap">{{ formatDateTime(fee.updated_at) }}</td>
                            <td class="text-xs whitespace-nowrap text-right space-x-2">
                                <a v-if="fee.fee_receipt?.proof_url" :href="fee.fee_receipt.proof_url" target="_blank" rel="noopener" class="link-brand">Proof</a>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="approve(fee.id)" class="text-green-700 font-semibold">Approve</button>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="reject(fee.id)" class="text-red-600 font-semibold">Reject</button>
                                <span v-else-if="fee.status === 'approved'" class="text-green-700 font-semibold">Approved</span>
                            </td>
                        </tr>
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
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { formatDateTime } from '@/support/calendarDates.js';

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

function approve(schoolEventFeeId) {
    if (!confirm('Approve this school event fee and post it to the event income account?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/fest/payments/${schoolEventFeeId}/approve`, {}, { preserveScroll: true });
}

function reject(schoolEventFeeId) {
    const reason = prompt('Rejection reason (optional):');
    if (reason === null) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/fest/payments/${schoolEventFeeId}/reject`, {
        rejection_reason: reason || null,
    }, { preserveScroll: true });
}
</script>
