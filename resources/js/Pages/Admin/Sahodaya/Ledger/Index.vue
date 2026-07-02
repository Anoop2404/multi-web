<template>
    <SahodayaAdminLayout title="Ledger" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="Ledger"
            eyebrow="Finance"
            description="Combined accounts ledger with separate heads for membership, each event, training, and expenses."
        >
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/ledger/reports`" class="btn-secondary text-sm">Reports →</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="applyYearFilter" class="card mb-4 flex flex-wrap items-end gap-3">
            <FormField label="Academic year">
                <template #default="{ id }">
                    <select :id="id" v-model="financialYearId" class="field max-w-xs">
                        <option value="">All years</option>
                        <option v-for="y in academicYears" :key="y.id" :value="String(y.id)">
                            {{ y.label }} ({{ y.status }})
                        </option>
                    </select>
                </template>
            </FormField>
            <button type="submit" class="btn-secondary text-sm">Apply</button>
        </form>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs text-emerald-700 uppercase font-bold tracking-wide">Total credits</p>
                <p class="text-2xl font-bold text-emerald-900 mt-1">₹{{ fmt(summary?.credit) }}</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs text-red-700 uppercase font-bold tracking-wide">Total debits</p>
                <p class="text-2xl font-bold text-red-900 mt-1">₹{{ fmt(summary?.debit) }}</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs text-sky-700 uppercase font-bold tracking-wide">Net balance</p>
                <p class="text-2xl font-bold mt-1" :class="(summary?.credit - summary?.debit) >= 0 ? 'text-sky-900' : 'text-red-700'">
                    ₹{{ fmt((summary?.credit ?? 0) - (summary?.debit ?? 0)) }}
                </p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <section class="card space-y-3">
                <h3 class="section-title">Account heads</h3>
                <form @submit.prevent="addHead" class="space-y-2">
                    <FormField label="Code" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="headForm.code" class="field" placeholder="MEMBERSHIP" required>
                        </template>
                    </FormField>
                    <FormField label="Head name" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="headForm.name" class="field" placeholder="Membership income" required>
                        </template>
                    </FormField>
                    <FormField label="Type">
                        <template #default="{ id }">
                            <select :id="id" v-model="headForm.type" class="field">
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                            </select>
                        </template>
                    </FormField>
                    <button type="submit" class="btn-primary w-full text-sm">Add head</button>
                </form>
                <ul class="divide-y text-sm border-t border-slate-100 pt-2">
                    <li v-for="h in heads" :key="h.id" class="py-2 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="font-mono text-xs text-slate-500">{{ h.code }}</span>
                            <span class="ml-2">{{ h.name }}</span>
                            <span v-if="h.category" class="ml-1 text-[10px] uppercase text-slate-400">· {{ categoryLabels[h.category] ?? h.category }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ h.type }}</span>
                            <button type="button" @click="deleteHead(h)" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                        </div>
                    </li>
                    <li v-if="!heads.length" class="py-4 text-center text-slate-400 text-xs">No heads yet</li>
                </ul>
            </section>

            <section class="card space-y-3">
                <h3 class="section-title">Post manual transaction</h3>
                <p class="section-desc">Counter defaults to CASH-BANK unless you pick another head.</p>
                <form @submit.prevent="postTransaction" class="space-y-2">
                    <FormField label="Primary account head" required>
                        <template #default="{ id }">
                            <select :id="id" v-model="txForm.account_head_id" class="field" required>
                                <option value="">Select head</option>
                                <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.code }} — {{ h.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField label="Counter account">
                        <template #default="{ id }">
                            <select :id="id" v-model="txForm.counter_account_head_id" class="field">
                                <option value="">Cash & Bank (default)</option>
                                <option v-for="h in heads" :key="'c-'+h.id" :value="h.id">{{ h.code }} — {{ h.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <div class="grid grid-cols-2 gap-2">
                        <FormField label="Entry type">
                            <template #default="{ id }">
                                <select :id="id" v-model="txForm.entry_type" class="field">
                                    <option value="credit">Credit (+)</option>
                                    <option value="debit">Debit (−)</option>
                                </select>
                            </template>
                        </FormField>
                        <FormField label="Amount (₹)" required>
                            <template #default="{ id }">
                                <input :id="id" v-model="txForm.amount" type="number" min="0.01" step="0.01" class="field" required>
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Transaction date" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="txForm.transaction_date" type="date" class="field" required>
                        </template>
                    </FormField>
                    <FormField label="Description">
                        <template #default="{ id }">
                            <input :id="id" v-model="txForm.description" class="field" placeholder="Optional note">
                        </template>
                    </FormField>
                    <button type="submit" class="btn-primary w-full text-sm">Post transaction</button>
                </form>
            </section>

            <section class="card overflow-hidden !p-0 lg:col-span-1">
                <div class="flex items-center justify-between p-4 border-b border-slate-100">
                    <h3 class="section-title !mb-0">Recent transactions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Head</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transactionRows" :key="t.id">
                                <td class="text-xs">{{ t.transaction_date }}</td>
                                <td class="text-xs">
                                    <span :class="t.entry_type === 'credit' ? 'text-emerald-700' : 'text-red-600'" class="font-semibold">
                                        {{ t.entry_type === 'credit' ? '+' : '−' }}
                                    </span>
                                    {{ t.account_head?.name }}
                                </td>
                                <td class="text-right font-mono text-xs">₹{{ fmt(t.amount) }}</td>
                            </tr>
                            <tr v-if="!transactionRows.length">
                                <td colspan="3" class="p-6 text-center text-slate-400 text-xs">No transactions yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="transactions?.links?.length > 3" class="p-3 border-t border-slate-100 flex flex-wrap gap-1">
                    <Link
                        v-for="link in transactions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1 rounded-lg text-xs"
                        :class="[
                            link.active ? 'bg-sky-100 text-sky-800 font-semibold' : 'text-slate-600 hover:bg-slate-100',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </section>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    heads: Array, transactions: [Array, Object], summary: Object,
    categoryLabels: { type: Object, default: () => ({}) },
    academicYears: { type: Array, default: () => [] },
    filterFinancialYearId: { type: Number, default: null },
});

const transactionRows = computed(() => {
    if (Array.isArray(props.transactions)) {
        return props.transactions;
    }

    return props.transactions?.data ?? [];
});

const financialYearId = ref(props.filterFinancialYearId ? String(props.filterFinancialYearId) : '');

function applyYearFilter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/ledger`, {
        financial_year_id: financialYearId.value || undefined,
    }, { preserveScroll: true });
}

const headForm = useForm({ code: '', name: '', type: 'income' });
const txForm = useForm({ account_head_id: '', counter_account_head_id: '', entry_type: 'credit', amount: '', transaction_date: today(), description: '' });

function today() {
    return new Date().toISOString().slice(0, 10);
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addHead() {
    headForm.post(`/sahodaya-admin/${props.sahodaya.id}/ledger/heads`, {
        preserveScroll: true,
        onSuccess: () => headForm.reset({ type: 'income' }),
    });
}

function deleteHead(head) {
    if (!confirm(`Delete head "${head.name}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/ledger/heads/${head.id}`, { preserveScroll: true });
}

function postTransaction() {
    txForm.post(`/sahodaya-admin/${props.sahodaya.id}/ledger/transactions`, {
        preserveScroll: true,
        onSuccess: () => txForm.reset({ account_head_id: '', counter_account_head_id: '', entry_type: 'credit', transaction_date: today() }),
    });
}
</script>
