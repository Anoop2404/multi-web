<template>
    <SahodayaAdminLayout :title="`${program.title} — Payment Ledger`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${program.title} — Payment Ledger`" eyebrow="Training finance"
                    description="Teacher registration fee collections and ledger postings for this program only.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">← Program</Link>
            </template>
        </PageHeader>

        <div class="card mb-4 bg-indigo-50 border-indigo-100 text-sm text-indigo-900">
            <p class="font-semibold">Ledger account head</p>
            <p class="mt-1 font-mono text-xs">{{ accountCode }} — {{ accountName }}</p>
            <p class="text-xs text-indigo-700 mt-2">Each training program has its own income head — separate from Talent Search exams, fest events, and membership.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold">₹{{ fmt(summary.total_due) }}</p>
                <p class="text-xs text-gray-500">Total due</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">₹{{ fmt(summary.collected) }}</p>
                <p class="text-xs text-gray-500">Collected (approved)</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-indigo-700">₹{{ fmt(summary.ledger_credits) }}</p>
                <p class="text-xs text-gray-500">Posted to ledger</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-amber-600">{{ summary.awaiting }}</p>
                <p class="text-xs text-gray-500">Awaiting approval</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <section class="card card--flush overflow-hidden">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">Registrations & fees</h3>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>School</th>
                                <th>Status</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in registrations" :key="i">
                                <td>{{ row.teacher }}</td>
                                <td class="text-xs text-slate-500">{{ row.school || '—' }}</td>
                                <td><span class="text-xs font-semibold capitalize">{{ row.status }}</span></td>
                                <td class="text-right font-mono">₹{{ fmt(row.amount) }}</td>
                            </tr>
                            <tr v-if="!registrations.length">
                                <td colspan="4" class="p-6 text-center text-slate-400">No registrations yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card card--flush overflow-hidden">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">Ledger transactions</h3>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transactions" :key="t.id">
                                <td class="text-xs">{{ t.transaction_date }}</td>
                                <td class="text-xs capitalize">{{ t.entry_type }}</td>
                                <td class="text-right font-mono">₹{{ fmt(t.amount) }}</td>
                            </tr>
                            <tr v-if="!transactions.length">
                                <td colspan="3" class="p-6 text-center text-slate-400">No ledger entries yet — approve fees to post</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    accountCode: String,
    accountName: String,
    summary: Object,
    registrations: { type: Array, default: () => [] },
    transactions: { type: Array, default: () => [] },
});

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
</script>
