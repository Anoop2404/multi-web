<template>
    <SahodayaAdminLayout
        title="Ledger"
        :sahodaya="sahodaya"
        :publicUrl="publicUrl"
        :pendingPaymentsCount="pendingPaymentsCount"
        :show-header-title="false"
    >
        <PageHeader
            title="Ledger"
            eyebrow="Finance"
            description="Combined accounts ledger with separate heads for membership, each event, training, and expenses."
        >
            <template #actions>
                <Link
                    :href="`/sahodaya-admin/${sahodaya.id}/finance/workspace`"
                    class="btn-primary text-sm"
                    >Add income / expense</Link
                >
                <Link
                    :href="`/sahodaya-admin/${sahodaya.id}/ledger/opening-balances`"
                    class="btn-secondary text-sm"
                    >Opening balances</Link
                >
                <Link
                    :href="`/sahodaya-admin/${sahodaya.id}/finance/payables`"
                    class="btn-secondary text-sm"
                    >Payables</Link
                >
                <Link
                    :href="`/sahodaya-admin/${sahodaya.id}/finance/financial-statements`"
                    class="btn-secondary text-sm"
                    >Financial statements</Link
                >
                <Link
                    :href="`/sahodaya-admin/${sahodaya.id}/ledger/reports`"
                    class="btn-secondary text-sm"
                    >Reports →</Link
                >
            </template>
        </PageHeader>

        <form
            @submit.prevent="applyYearFilter"
            class="card mb-4 flex flex-wrap items-end gap-3"
        >
            <FormField label="Financial year">
                <template #default="{ id }">
                    <SearchableSelect
                        :id="id"
                        v-model="financialYearId"
                        class="max-w-xs"
                        :options="academicYearOptions"
                        :all-option="true"
                        all-label="All years"
                    />
                </template>
            </FormField>
            <label
                >From<input
                    v-model="transactionFilters.from"
                    type="date"
                    class="field"
            /></label>
            <label
                >To<input
                    v-model="transactionFilters.to"
                    type="date"
                    class="field"
            /></label>
            <label
                >Account<select
                    v-model="transactionFilters.account_head_id"
                    class="field"
                >
                    <option value="">All accounts</option>
                    <option v-for="h in heads" :key="h.id" :value="h.id">
                        {{ h.name }}
                    </option>
                </select></label
            >
            <button type="submit" class="btn-secondary text-sm">Apply</button>
        </form>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card">
                <p class="section-desc">Cash & bank balance</p>
                <p class="text-2xl font-bold">
                    ₹{{ fmt(financialOverview?.cash) }}
                </p>
            </div>
            <div class="card">
                <p class="section-desc">Net income (after reversals)</p>
                <p class="text-2xl font-bold">
                    ₹{{ fmt(financialOverview?.income) }}
                </p>
            </div>
            <div class="card">
                <p class="section-desc">Expenses (after reversals)</p>
                <p class="text-2xl font-bold">
                    ₹{{ fmt(financialOverview?.expense) }}
                </p>
            </div>
        </div>
        <p class="text-sm text-slate-500 mb-4">
            Journal integrity: debits ₹{{ fmt(summary?.debit) }} · credits ₹{{
                fmt(summary?.credit)
            }}
            · difference ₹{{
                fmt((summary?.debit ?? 0) - (summary?.credit ?? 0))
            }}. This difference is not cash available.
        </p>
        <div class="grid lg:grid-cols-2 gap-4">
            <details class="card space-y-3">
                <summary class="section-title cursor-pointer">
                    Manage account heads
                </summary>
                <form @submit.prevent="addHead" class="space-y-2">
                    <FormField label="Code" required>
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="headForm.code"
                                class="field"
                                placeholder="MEMBERSHIP"
                                required
                            />
                        </template>
                    </FormField>
                    <FormField label="Head name" required>
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="headForm.name"
                                class="field"
                                placeholder="Membership income"
                                required
                            />
                        </template>
                    </FormField>
                    <FormField label="Type">
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="headForm.type"
                                :all-option="false"
                                :options="[
                                    { value: 'income', label: 'Income' },
                                    { value: 'expense', label: 'Expense' },
                                    { value: 'asset', label: 'Asset' },
                                    { value: 'liability', label: 'Liability' },
                                ]"
                            />
                        </template>
                    </FormField>
                    <p
                        v-for="(error, key) in headForm.errors"
                        :key="key"
                        class="text-red-700 text-sm"
                    >
                        {{ error }}
                    </p>
                    <button
                        :disabled="headForm.processing"
                        type="submit"
                        class="btn-primary w-full text-sm"
                    >
                        Add head
                    </button>
                </form>
                <ul class="divide-y text-sm border-t border-slate-100 pt-2">
                    <li
                        v-for="h in heads"
                        :key="h.id"
                        class="py-2 flex items-center justify-between gap-2"
                    >
                        <div class="min-w-0">
                            <span class="font-mono text-xs text-slate-500">{{
                                h.code
                            }}</span>
                            <span class="ml-2">{{ h.name }}</span>
                            <span
                                v-if="h.category"
                                class="ml-1 text-[10px] uppercase text-slate-400"
                                >·
                                {{
                                    categoryLabels[h.category] ?? h.category
                                }}</span
                            >
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span
                                class="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-600"
                                >{{ h.type }}</span
                            >
                            <button
                                type="button"
                                @click="renameHead(h)"
                                class="text-indigo-700 text-xs"
                            >
                                Rename
                            </button>
                            <button
                                type="button"
                                @click="deleteHead(h)"
                                :disabled="
                                    protectedHeadCodes.includes(h.code) ||
                                    h.code.startsWith('BANK-') ||
                                    h.code === 'ADVANCES'
                                "
                                class="text-red-500 hover:text-red-700 text-xs"
                            >
                                Remove
                            </button>
                        </div>
                    </li>
                    <li
                        v-if="!heads.length"
                        class="py-4 text-center text-slate-400 text-xs"
                    >
                        No heads yet
                    </li>
                </ul>
            </details>

            <details class="card space-y-3">
                <summary class="section-title cursor-pointer">
                    Advanced journal entry
                </summary>
                <p class="section-desc">
                    Counter defaults to CASH-BANK unless you pick another head.
                </p>
                <form @submit.prevent="postTransaction" class="space-y-2">
                    <FormField label="Primary account head" required>
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="txForm.account_head_id"
                                :required="true"
                                :all-option="true"
                                all-label="Select head"
                                :options="headOptions"
                            />
                        </template>
                    </FormField>
                    <FormField label="Counter account">
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="txForm.counter_account_head_id"
                                :all-option="true"
                                all-label="Cash & Bank (default)"
                                :options="headOptions"
                            />
                        </template>
                    </FormField>
                    <div class="grid grid-cols-2 gap-2">
                        <FormField label="Entry type">
                            <template #default="{ id }">
                                <SearchableSelect
                                    :id="id"
                                    v-model="txForm.entry_type"
                                    :all-option="false"
                                    :options="[
                                        { value: 'credit', label: 'Credit' },
                                        { value: 'debit', label: 'Debit' },
                                    ]"
                                />
                            </template>
                        </FormField>
                        <FormField label="Amount (₹)" required>
                            <template #default="{ id }">
                                <input
                                    :id="id"
                                    v-model="txForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="field"
                                    required
                                />
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Transaction date" required>
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="txForm.transaction_date"
                                type="date"
                                class="field"
                                required
                            />
                        </template>
                    </FormField>
                    <FormField label="Description">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="txForm.description"
                                class="field"
                                placeholder="Optional note"
                            />
                        </template>
                    </FormField>
                    <p
                        v-for="(error, key) in txForm.errors"
                        :key="key"
                        class="text-red-700 text-sm"
                    >
                        {{ error }}
                    </p>
                    <button
                        :disabled="txForm.processing"
                        type="submit"
                        class="btn-primary w-full text-sm"
                    >
                        Post transaction
                    </button>
                </form>
            </details>

            <section class="card overflow-hidden !p-0 lg:col-span-2">
                <div
                    class="flex items-center justify-between p-4 border-b border-slate-100"
                >
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
                                <td class="text-xs whitespace-nowrap">
                                    {{ formatCalendarDate(t.transaction_date) }}
                                </td>
                                <td class="text-xs">
                                    <span
                                        :class="
                                            t.entry_type === 'credit'
                                                ? 'text-emerald-700'
                                                : 'text-red-600'
                                        "
                                        class="font-semibold"
                                    >
                                        {{
                                            t.entry_type === "credit"
                                                ? "Cr"
                                                : "Dr"
                                        }}
                                    </span>
                                    {{ t.account_head?.name }}
                                </td>
                                <td class="text-right font-mono text-xs">
                                    ₹{{ fmt(t.amount) }}
                                </td>
                            </tr>
                            <tr v-if="!transactionRows.length">
                                <td
                                    colspan="3"
                                    class="p-6 text-center text-slate-400 text-xs"
                                >
                                    No transactions yet
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="transactions?.links?.length > 3"
                    class="p-3 border-t border-slate-100 flex flex-wrap gap-1"
                >
                    <Link
                        v-for="link in transactions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-1 rounded-lg text-xs"
                        :class="[
                            link.active
                                ? 'bg-sky-100 text-sky-800 font-semibold'
                                : 'text-slate-600 hover:bg-slate-100',
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
import { Link, useForm, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import SahodayaAdminLayout from "@/Layouts/SahodayaAdminLayout.vue";
import SearchableSelect from "@/Components/ui/SearchableSelect.vue";
import { formatCalendarDate } from "@/support/calendarDates.js";
import { useConfirm } from "@/composables/useConfirm";

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    heads: Array,
    transactions: [Array, Object],
    summary: Object,
    financialOverview: Object,
    protectedHeadCodes: { type: Array, default: () => [] },
    categoryLabels: { type: Object, default: () => ({}) },
    academicYears: { type: Array, default: () => [] },
    transactionFilters: { type: Object, default: () => ({}) },
    filterFinancialYearId: { type: Number, default: null },
});

const { confirm, prompt } = useConfirm();

const transactionRows = computed(() => {
    if (Array.isArray(props.transactions)) {
        return props.transactions;
    }

    return props.transactions?.data ?? [];
});

const transactionFilters = ref({
    from: "",
    to: "",
    account_head_id: "",
    ...props.transactionFilters,
});
const financialYearId = ref(
    props.filterFinancialYearId ? String(props.filterFinancialYearId) : "",
);

const academicYearOptions = computed(() =>
    props.academicYears.map((y) => ({
        value: String(y.id),
        label: `${y.label} (${y.status})`,
    })),
);

const headOptions = computed(() =>
    props.heads.map((h) => ({
        value: h.id,
        label: `${h.code} — ${h.name}`,
    })),
);

function applyYearFilter() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/ledger`,
        {
            financial_year_id: financialYearId.value || "",
            ...transactionFilters.value,
        },
        { preserveScroll: true },
    );
}

const headForm = useForm({ code: "", name: "", type: "income" });
const txForm = useForm({
    account_head_id: "",
    counter_account_head_id: "",
    entry_type: "credit",
    amount: "",
    transaction_date: today(),
    description: "",
});

function today() {
    return new Date().toLocaleDateString("en-CA");
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function addHead() {
    headForm.post(`/sahodaya-admin/${props.sahodaya.id}/ledger/heads`, {
        preserveScroll: true,
        onSuccess: () => headForm.reset({ type: "income" }),
    });
}

async function renameHead(head) {
    const name = await prompt({
        title: "Rename account",
        message: "Account name",
        inputValue: head.name,
        inputRequired: true,
    });
    if (name?.trim())
        router.patch(
            `/sahodaya-admin/${props.sahodaya.id}/ledger/heads/${head.id}`,
            { name: name.trim() },
            { preserveScroll: true },
        );
}

async function deleteHead(head) {
    if (
        !(await confirm({
            message: `Delete head "${head.name}"?`,
            destructive: true,
        }))
    )
        return;
    router.delete(
        `/sahodaya-admin/${props.sahodaya.id}/ledger/heads/${head.id}`,
        { preserveScroll: true },
    );
}

function postTransaction() {
    txForm.post(`/sahodaya-admin/${props.sahodaya.id}/ledger/transactions`, {
        preserveScroll: true,
        onSuccess: () =>
            txForm.reset({
                account_head_id: "",
                counter_account_head_id: "",
                entry_type: "credit",
                transaction_date: today(),
            }),
    });
}
</script>
