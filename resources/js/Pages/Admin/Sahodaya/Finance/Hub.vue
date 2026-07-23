<template>
    <SahodayaAdminLayout title="Finance hub" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Finance hub" eyebrow="Accounts"
                    description="Unified view of pending payments across membership, fest events, Talent Search, and training.">
        </PageHeader>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4">
                <p class="text-xs uppercase text-slate-500 font-semibold">Pending verifications</p>
                <p class="text-2xl font-bold mt-1">{{ summary.total_pending }}</p>
            </div>
            <div class="card card--muted !py-4">
                <p class="text-xs uppercase text-slate-500 font-semibold">Fest outstanding</p>
                <p class="text-2xl font-bold mt-1">₹{{ fmt(summary.fest_outstanding) }}</p>
                <p class="text-xs text-amber-700 mt-1">{{ summary.fest_pending }} awaiting verify</p>
                <!-- Money already owed BACK to schools (rejected/cancelled paid items) — shown
                     separately rather than netted into the total above, so that figure keeps
                     its existing meaning. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14. -->
                <p v-if="summary.fest_credit > 0" class="text-xs text-emerald-700 mt-0.5 font-semibold">
                    ₹{{ fmt(summary.fest_credit) }} owed back to schools (credit)
                </p>
            </div>
            <div class="card card--muted !py-4">
                <p class="text-xs uppercase text-slate-500 font-semibold">Membership outstanding</p>
                <p class="text-2xl font-bold mt-1">₹{{ fmt(summary.membership_outstanding) }}</p>
                <p class="text-xs text-amber-700 mt-1">{{ summary.membership_pending }} awaiting verify</p>
            </div>
            <div class="card card--muted !py-4">
                <p class="text-xs uppercase text-slate-500 font-semibold">Talent Search outstanding</p>
                <p class="text-2xl font-bold mt-1">₹{{ fmt(summary.mcq_outstanding) }}</p>
                <p class="text-xs text-amber-700 mt-1">{{ summary.mcq_pending }} awaiting verify</p>
            </div>
            <div class="card card--muted !py-4">
                <p class="text-xs uppercase text-slate-500 font-semibold">Training outstanding</p>
                <p class="text-2xl font-bold mt-1">₹{{ fmt(summary.training_outstanding) }}</p>
                <p class="text-xs text-amber-700 mt-1">{{ summary.training_pending }} awaiting verify</p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4 mb-8">
            <Link :href="links.unified_payments" class="card hover:border-[#0f3d7a]/30 transition !p-5 block border-2 border-[#0f3d7a]/20">
                <h3 class="font-semibold text-[#0f3d7a]">All payments register</h3>
                <p class="text-sm text-slate-600 mt-1">Unified view of verified payments, receipts, and email delivery status.</p>
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/finance/receipt-emails`" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Receipt email delivery</h3>
                <p class="text-sm text-slate-600 mt-1">Failed or pending receipt emails with retry visibility.</p>
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/reports/hub`" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Reports catalogue</h3>
                <p class="text-sm text-slate-600 mt-1">Browse mapped ERP reports across finance, membership, and verification.</p>
            </Link>
            <Link :href="links.fest_payments" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Fest event payments</h3>
                <p class="text-sm text-slate-600 mt-1">Verify school event fee proofs across all programs.</p>
            </Link>
            <Link :href="links.membership_payments" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Membership payments</h3>
                <p class="text-sm text-slate-600 mt-1">Annual registration fee verification queue.</p>
            </Link>
            <Link :href="links.mcq_payments" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Talent Search payments</h3>
                <p class="text-sm text-slate-600 mt-1">School batch fee proofs — each exam has its own ledger account.</p>
            </Link>
            <Link :href="links.training_programs" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Training programs</h3>
                <p class="text-sm text-slate-600 mt-1">Manage programs and open per-program payment ledgers.</p>
            </Link>
            <Link :href="links.receivables" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Accounts receivable</h3>
                <p class="text-sm text-slate-600 mt-1">Outstanding dues by school across programs.</p>
            </Link>
            <Link :href="links.payables" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Accounts payable</h3>
                <p class="text-sm text-slate-600 mt-1">Amounts owed to vendors, state, or others — with due dates.</p>
            </Link>
            <Link :href="links.opening_balances" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Opening balances</h3>
                <p class="text-sm text-slate-600 mt-1">Carry forward cash and account balances per academic year.</p>
            </Link>
            <Link :href="links.ledger" class="card hover:border-[#0f3d7a]/30 transition !p-5 block">
                <h3 class="font-semibold text-[#0f3d7a]">Accounts ledger</h3>
                <p class="text-sm text-slate-600 mt-1">Double-entry ledger and income by category.</p>
            </Link>
        </div>

        <section v-if="monthlyCollection?.length" class="card !p-5 mb-8">
            <h3 class="section-title mb-3">Monthly collection (last 12 months)</h3>
            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                <div v-for="m in monthlyCollection" :key="m.month" class="rounded-lg border border-slate-100 p-2 text-center">
                    <p class="text-[10px] uppercase text-slate-400">{{ m.month }}</p>
                    <p class="text-sm font-bold text-[#0f3d7a]">₹{{ fmt(m.amount) }}</p>
                    <p class="text-[10px] text-slate-500">{{ m.count }} receipts</p>
                </div>
            </div>
        </section>

        <section v-if="ledgerByCategory?.length" class="card !p-5">
            <h3 class="section-title mb-3">Ledger by category</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Credits</th>
                        <th>Debits</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in ledgerByCategory" :key="row.category">
                        <td>{{ categoryLabel(row.category) }}</td>
                        <td>₹{{ fmt(row.credit) }}</td>
                        <td>₹{{ fmt(row.debit) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    summary: Object,
    links: Object,
    ledgerByCategory: Array,
    categoryLabels: { type: Object, default: () => ({}) },
    monthlyCollection: { type: Array, default: () => [] },
});

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN');
}

function categoryLabel(key) {
    return props.categoryLabels?.[key] ?? key;
}
</script>
