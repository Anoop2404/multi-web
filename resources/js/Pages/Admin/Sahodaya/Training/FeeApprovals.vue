<template>
    <SahodayaAdminLayout :title="`${program.title} — Fee approvals`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Fee approvals"
                    :description="hasFee
                        ? `Fees are collected at the venue. Teachers can attend first — record / approve payments here later.`
                        : `This programme has no fee — registrations confirm automatically.`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations`"
                      class="btn-secondary text-sm">
                    Registrations
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/ledger`"
                      class="btn-secondary text-sm">
                    Payment ledger
                </Link>
            </template>
        </PageHeader>

        <div v-if="hasFee" class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-amber-600">{{ counts.awaiting_proof }}</p>
                <p class="text-xs text-gray-500">Awaiting proof / mark paid</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ counts.pending_approval }}</p>
                <p class="text-xs text-gray-500">Pending approval</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">{{ counts.approved }}</p>
                <p class="text-xs text-gray-500">Paid / confirmed</p>
            </div>
        </div>

        <div v-if="!hasFee" class="card text-sm text-gray-500 py-8 text-center">
            No fee configured for this programme. Registrations are confirmed on submit.
        </div>

        <div v-else class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[760px] text-sm">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Source</th>
                            <th>Due</th>
                            <th>Proof</th>
                            <th>Reg status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td>
                                <div class="font-medium">{{ row.teacher_name || `#${row.id}` }}</div>
                                <div class="text-xs text-gray-400">{{ row.teacher_email || '' }}</div>
                            </td>
                            <td>{{ row.school_name || '—' }}</td>
                            <td>
                                <span v-if="row.source === 'qr'"
                                      class="text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                                    QR
                                </span>
                                <span v-else class="text-xs text-gray-400 capitalize">{{ row.source || 'portal' }}</span>
                            </td>
                            <td class="font-mono text-xs">
                                ₹{{ fmt(row.amount_due) }}
                                <div v-if="row.amount_paid > 0" class="text-green-700">Paid ₹{{ fmt(row.amount_paid) }}</div>
                                <div v-if="row.outstanding > 0 && row.amount_paid > 0" class="text-amber-700">
                                    Bal ₹{{ fmt(row.outstanding) }}
                                </div>
                            </td>
                            <td>
                                <span v-if="row.receipt?.status === 'uploaded'" class="text-xs text-amber-700 font-semibold">Uploaded</span>
                                <span v-else-if="row.receipt?.status === 'approved'" class="text-xs text-green-700 font-semibold">Approved</span>
                                <span v-else-if="row.receipt?.status === 'rejected'" class="text-xs text-red-600 font-semibold">Rejected</span>
                                <span v-else-if="row.fee_status === 'auto_approved'" class="text-xs text-indigo-700 font-semibold">Auto approved</span>
                                <span v-else class="text-xs text-gray-400">No proof</span>
                                <div v-if="row.receipt?.transaction_ref" class="text-[10px] text-gray-400 mt-0.5">
                                    {{ row.receipt.transaction_ref }}
                                </div>
                            </td>
                            <td class="capitalize text-gray-600">{{ row.status }}</td>
                            <td class="text-right">
                                <div class="flex justify-end items-center gap-2 flex-wrap">
                                    <a v-if="row.receipt?.has_file"
                                       :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${row.id}/fee/proof`"
                                       target="_blank" rel="noopener"
                                       class="text-xs text-indigo-600 font-semibold">
                                        View proof ↗
                                    </a>
                                    <button v-if="row.can_approve"
                                            type="button" @click="approve(row)"
                                            class="text-xs text-green-600 font-semibold">
                                        Approve payment
                                    </button>
                                    <button v-if="row.can_reject"
                                            type="button" @click="reject(row)"
                                            class="text-xs text-red-600 font-semibold">
                                        Reject
                                    </button>
                                    <button v-if="row.can_record"
                                            type="button" @click="record(row)"
                                            class="text-xs text-indigo-700 font-semibold border border-indigo-200 px-2 py-1 rounded">
                                        Record venue payment
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="7" class="text-center text-gray-400 py-8">No registrations yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    hasFee: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function approve(row) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${row.id}/fee/approve`,
        {},
        { preserveScroll: true },
    );
}

function reject(row) {
    const reason = window.prompt('Rejection reason (optional):') ?? '';
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${row.id}/fee/reject`,
        { rejection_reason: reason || null },
        { preserveScroll: true },
    );
}

function record(row) {
    const ref = window.prompt('Transaction ref / note (optional):') ?? '';
    if (ref === null) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${row.id}/fee/record`,
        {
            transaction_ref: ref || 'Recorded by Sahodaya',
            amount: row.outstanding,
        },
        { preserveScroll: true },
    );
}
</script>
