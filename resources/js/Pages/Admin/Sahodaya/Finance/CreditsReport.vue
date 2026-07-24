<template>
    <SahodayaAdminLayout title="Fee Credits & Refunds" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader title="Fee Credits &amp; Refunds" eyebrow="Finance"
                    description="Standalone register of all fee credits issued to member schools and recorded out-of-platform bank payouts.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance/payments`" class="btn-secondary text-sm flex items-center gap-1.5 shadow-xs">
                    ← Back to Payments
                </Link>
            </template>
        </PageHeader>

        <!-- Executive Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card !p-4 bg-slate-900 text-white rounded-xl shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Credits Issued</p>
                <p class="text-2xl font-black mt-1">₹{{ fmt(stats.total_issued) }}</p>
            </div>
            <div class="card !p-4 bg-emerald-50 border border-emerald-200 text-emerald-950 rounded-xl shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Outstanding Credit Balance</p>
                <p class="text-2xl font-black mt-1 text-emerald-900">₹{{ fmt(stats.outstanding) }}</p>
                <p class="text-[11px] text-emerald-700 mt-0.5 font-medium">Available toward future fees</p>
            </div>
            <div class="card !p-4 bg-blue-50 border border-blue-200 text-blue-950 rounded-xl shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-blue-800">Closed / Applied</p>
                <p class="text-2xl font-black mt-1 text-blue-900">₹{{ fmt(stats.closed) }}</p>
                <p class="text-[11px] text-blue-700 mt-0.5 font-medium">Consumed against fees</p>
            </div>
            <div class="card !p-4 bg-purple-50 border border-purple-200 text-purple-950 rounded-xl shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-purple-800">Out-of-Platform Bank Payouts</p>
                <p class="text-2xl font-black mt-1 text-purple-900">₹{{ fmt(stats.paid_out) }}</p>
                <p class="text-[11px] text-purple-700 mt-0.5 font-medium">Recorded bank refunds</p>
            </div>
        </div>

        <!-- Credits List -->
        <div class="card card--flush overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Issued Fee Credits</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Every credit row generated from fee reductions or cancellations.</p>
                </div>
            </div>

            <div v-if="!credits.length" class="p-8 text-center text-slate-400 text-sm">
                No fee credits issued yet.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="data-table text-sm w-full">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 text-left text-xs uppercase font-bold tracking-wider">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">School</th>
                            <th class="px-4 py-3">Source / Program</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="c in credits" :key="`${c.credit_type}-${c.id}`" class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                                {{ formatCalendarDate(c.created_at) }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ c.school_name || '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700 font-medium">
                                {{ c.source_label }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-xs truncate" :title="c.reason">
                                {{ c.reason || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-900 whitespace-nowrap">
                                ₹{{ fmt(c.amount) }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
                                      :class="c.status === 'outstanding' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">
                                    {{ c.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button v-if="c.status === 'outstanding'" type="button"
                                        class="btn-secondary text-xs !py-1 !px-2.5 font-semibold text-purple-700 border-purple-200 bg-purple-50 hover:bg-purple-100"
                                        @click="openPayoutModal(c)">
                                    Record Bank Payout
                                </button>
                                <span v-else class="text-xs text-slate-400">Closed</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payout Modal -->
        <div v-if="selectedCredit" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs" @click="closePayoutModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-base font-bold text-slate-900">Record Out-of-Platform Bank Payout</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Record a bank transfer refund of <strong class="text-slate-900">₹{{ fmt(selectedCredit.amount) }}</strong> to {{ selectedCredit.school_name }}. This closes out the credit row.
                </p>

                <form class="mt-4 space-y-3" @submit.prevent="submitPayout">
                    <div>
                        <label class="form-label text-xs font-semibold text-slate-700">Bank Reference / UTR #</label>
                        <input v-model="payoutForm.bank_ref" type="text" class="form-input text-sm w-full mt-1" placeholder="e.g. UTR123456789" required />
                    </div>
                    <div>
                        <label class="form-label text-xs font-semibold text-slate-700">Notes / Remarks</label>
                        <textarea v-model="payoutForm.notes" rows="2" class="form-input text-sm w-full mt-1" placeholder="Optional notes for office record…"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-ghost text-xs" @click="closePayoutModal">Cancel</button>
                        <button type="submit" class="btn-primary text-xs" :disabled="submitting">
                            {{ submitting ? 'Saving…' : 'Confirm & Close Credit' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    credits: { type: Array, default: () => [] },
    payouts: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const selectedCredit = ref(null);
const submitting = ref(false);
const payoutForm = ref({
    bank_ref: '',
    notes: '',
});

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function openPayoutModal(credit) {
    selectedCredit.value = credit;
    payoutForm.value = { bank_ref: '', notes: '' };
}

function closePayoutModal() {
    selectedCredit.value = null;
}

function submitPayout() {
    if (!selectedCredit.value) return;

    submitting.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payments/credits/payout`, {
        credit_type: selectedCredit.value.credit_type,
        credit_id: selectedCredit.value.id,
        bank_ref: payoutForm.value.bank_ref,
        notes: payoutForm.value.notes,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
            closePayoutModal();
        },
    });
}
</script>
