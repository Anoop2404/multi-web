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
                    <SearchableSelect :id="id" v-model="financialYearId" class="max-w-xs"
                                       :options="academicYearOptions" :all-option="false" />
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
                            <SearchableSelect :id="id" v-model="form.account_head_id" :required="true"
                                               :all-option="true" all-label="Select head" :options="headOptions" />
                        </template>
                    </FormField>
                    <div class="grid grid-cols-2 gap-2">
                        <FormField label="Entry type">
                            <template #default="{ id }">
                                <SearchableSelect :id="id" v-model="form.entry_type" :all-option="false"
                                                   :options="[{ value: 'debit', label: 'Debit' }, { value: 'credit', label: 'Credit' }]" />
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
import { computed, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

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

const academicYearOptions = computed(() => props.academicYears.map(y => ({
    value: String(y.id),
    label: `${y.label} (${y.status})`,
})));

const headOptions = computed(() => props.heads.map(h => ({
    value: h.id,
    label: `${h.code} — ${h.name}`,
})));

const { confirm } = useConfirm();

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

async function remove(id) {
    if (!(await confirm({ message: 'Remove this opening balance and its ledger entries?', destructive: true }))) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/ledger/opening-balances/${id}`, { preserveScroll: true });
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
