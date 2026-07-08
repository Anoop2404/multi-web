<template>
    <SahodayaAdminLayout title="Opening balances" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Opening balances" eyebrow="Finance"
                    description="Carry forward cash, bank, and account balances at the start of each academic year. Posts balanced journals against OPENING-BAL.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/ledger`" class="btn-secondary text-sm">← Ledger</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="applyYear" class="card mb-4 flex flex-wrap items-end gap-3">
            <FormField label="Academic year">
                <template #default="{ id }">
                    <select :id="id" v-model="financialYearId" class="field max-w-xs">
                        <option v-for="y in academicYears" :key="y.id" :value="String(y.id)">
                            {{ y.label }} ({{ y.status }})
                        </option>
                    </select>
                </template>
            </FormField>
            <button type="submit" class="btn-secondary text-sm">Apply</button>
        </form>

        <div class="grid lg:grid-cols-2 gap-4">
            <section class="card space-y-3">
                <h3 class="section-title">Add / update opening balance</h3>
                <p class="section-desc text-xs">Use debit for assets (cash, bank). Use credit for income or liability balances carried forward.</p>
                <form @submit.prevent="saveOpening" class="space-y-2">
                    <FormField label="Account head" required>
                        <template #default="{ id }">
                            <select :id="id" v-model="form.account_head_id" class="field" required>
                                <option value="">Select head</option>
                                <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.code }} — {{ h.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <div class="grid grid-cols-2 gap-2">
                        <FormField label="Entry type">
                            <template #default="{ id }">
                                <select :id="id" v-model="form.entry_type" class="field">
                                    <option value="debit">Debit</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </template>
                        </FormField>
                        <FormField label="Amount (₹)" required>
                            <template #default="{ id }">
                                <input :id="id" v-model="form.amount" type="number" min="0.01" step="0.01" class="field" required>
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Notes">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.notes" class="field" placeholder="e.g. Opening cash from 2024-25">
                        </template>
                    </FormField>
                    <button type="submit" class="btn-primary w-full text-sm" :disabled="form.processing">Post opening balance</button>
                </form>
            </section>

            <section class="card card--flush overflow-hidden !p-0">
                <h3 class="section-title p-4 border-b border-slate-100 !mb-0">Recorded for this year</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in openingBalances" :key="row.id">
                            <td class="text-sm">
                                <span class="font-mono text-xs text-slate-500 block">{{ row.account_head?.code }}</span>
                                {{ row.account_head?.name }}
                            </td>
                            <td class="text-xs capitalize">{{ row.entry_type }}</td>
                            <td class="text-right font-mono">₹{{ fmt(row.amount) }}</td>
                            <td class="text-right">
                                <button type="button" class="text-red-600 text-xs" @click="remove(row.id)">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!openingBalances.length">
                            <td colspan="4" class="p-6 text-center text-slate-400 text-sm">No opening balances for this year yet</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    heads: Array,
    openingBalances: Array,
    academicYears: Array,
    filterFinancialYearId: Number,
});

const financialYearId = ref(String(props.filterFinancialYearId ?? props.academicYears[0]?.id ?? ''));

const form = useForm({
    financial_year_id: props.filterFinancialYearId,
    account_head_id: '',
    entry_type: 'debit',
    amount: '',
    notes: '',
});

function applyYear() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/ledger/opening-balances`, {
        financial_year_id: financialYearId.value,
    }, { preserveScroll: true });
}

function saveOpening() {
    form.financial_year_id = Number(financialYearId.value);
    form.post(`/sahodaya-admin/${props.sahodaya.id}/ledger/opening-balances`, {
        preserveScroll: true,
        onSuccess: () => form.reset('account_head_id', 'amount', 'notes'),
    });
}

function remove(id) {
    if (!confirm('Remove this opening balance and its ledger entries?')) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/ledger/opening-balances/${id}`, { preserveScroll: true });
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
