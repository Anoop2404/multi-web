<template>
    <SahodayaAdminLayout
        title="Payables"
        :sahodaya="sahodaya"
        :publicUrl="publicUrl"
        :pendingPaymentsCount="pendingPaymentsCount"
        :show-header-title="false"
    >
        <PageHeader
            title="Accounts payable"
            eyebrow="Finance"
            description="Track amounts the Sahodaya owes vendors, state bodies, or other parties — with due dates and ledger posting."
        >
            <template #actions>
                <Link
                    :href="`/sahodaya-admin/${sahodaya.id}/finance`"
                    class="btn-secondary text-sm"
                    >← Finance hub</Link
                >
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-2 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs uppercase text-slate-500 font-semibold">
                    Open balance
                </p>
                <p class="text-2xl font-bold text-red-800 mt-1">
                    ₹{{ fmt(totals.open_amount) }}
                </p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs uppercase text-slate-500 font-semibold">
                    Overdue items
                </p>
                <p class="text-2xl font-bold text-amber-700 mt-1">
                    {{ totals.overdue_count }}
                </p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <section class="card space-y-3 lg:col-span-1">
                <h3 class="section-title">Record payable</h3>
                <p class="section-desc text-xs">
                    Posts Dr expense / Cr ACC-PAYABLE when saved (unless you
                    uncheck ledger).
                </p>
                <form @submit.prevent="createPayable" class="space-y-2">
                    <FormField label="Vendor / payee" required>
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="createForm.vendor_name"
                                class="field"
                                required
                                placeholder="State board, printer, venue…"
                            />
                        </template>
                    </FormField>
                    <FormField label="Description">
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="createForm.description"
                                class="field"
                                placeholder="Optional detail"
                            />
                        </template>
                    </FormField>
                    <FormField label="Amount (₹)" required>
                        <template #default="{ id }">
                            <input
                                :id="id"
                                v-model="createForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="field"
                                required
                            />
                        </template>
                    </FormField>
                    <div class="grid grid-cols-2 gap-2">
                        <FormField label="Incurred date">
                            <template #default="{ id }">
                                <input
                                    :id="id"
                                    v-model="createForm.incurred_date"
                                    type="date"
                                    class="field"
                                />
                            </template>
                        </FormField>
                        <FormField label="Due date">
                            <template #default="{ id }">
                                <input
                                    :id="id"
                                    v-model="createForm.due_date"
                                    type="date"
                                    class="field"
                                />
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Expense head">
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="createForm.expense_head_id"
                                :options="expenseHeadOptions"
                                :all-option="true"
                                all-label="Administrative (default)"
                            />
                        </template>
                    </FormField>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            v-model="createForm.record_ledger"
                            class="mt-0.5"
                        />
                        Post to ledger now
                    </label>
                    <button
                        type="submit"
                        class="btn-primary w-full text-sm"
                        :disabled="createForm.processing"
                    >
                        Save payable
                    </button>
                </form>
            </section>

            <section
                class="card card--flush overflow-hidden !p-0 lg:col-span-2"
            >
                <div
                    class="p-4 border-b border-slate-100 flex flex-wrap gap-2 items-center"
                >
                    <h3 class="section-title !mb-0 mr-auto">Payables</h3>
                    <label
                        >Financial year<select
                            v-model="yearFilter"
                            class="field"
                            @change="applyFilter"
                        >
                            <option value="">All years</option>
                            <option
                                v-for="y in academicYears"
                                :key="y.id"
                                :value="y.id"
                            >
                                {{ y.label }}
                            </option>
                        </select></label
                    >
                    <SearchableSelect
                        v-model="statusFilter"
                        @change="applyFilter"
                        :options="[
                            { value: 'open', label: 'Open' },
                            { value: 'paid', label: 'Paid' },
                            { value: 'all', label: 'All' },
                        ]"
                        :all-option="false"
                        class="max-w-[10rem]"
                    />
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Due</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Balance</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in payables.data" :key="p.id">
                                <td>
                                    <p class="font-medium text-sm">
                                        {{ p.vendor_name }}
                                    </p>
                                    <p
                                        v-if="p.description"
                                        class="text-xs text-slate-500"
                                    >
                                        {{ p.description }}
                                    </p>
                                </td>
                                <td class="text-xs">
                                    {{ formatCalendarDate(p.due_date) }}
                                </td>
                                <td class="text-right font-mono text-sm">
                                    ₹{{ fmt(p.amount) }}
                                </td>
                                <td class="text-right font-mono text-sm">
                                    ₹{{
                                        fmt(
                                            p.status === "cancelled"
                                                ? 0
                                                : p.amount - p.amount_paid,
                                        )
                                    }}
                                </td>
                                <td>
                                    <span
                                        class="text-xs capitalize font-semibold"
                                        >{{ p.status }}</span
                                    >
                                    <details
                                        v-if="paymentHistory?.[p.id]?.length"
                                    >
                                        <summary class="text-xs cursor-pointer">
                                            Payment history
                                        </summary>
                                        <p
                                            v-for="t in paymentHistory[p.id]"
                                            :key="t.id"
                                            class="text-xs"
                                        >
                                            {{
                                                formatCalendarDate(
                                                    t.transaction_date,
                                                )
                                            }}
                                            · ₹{{ fmt(t.amount) }} ·
                                            {{ t.description }}
                                        </p>
                                    </details>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button
                                        v-if="
                                            p.status !== 'paid' &&
                                            p.status !== 'cancelled'
                                        "
                                        type="button"
                                        class="text-emerald-700 text-xs font-semibold mr-2"
                                        @click="openPayment(p)"
                                    >
                                        Record payment
                                    </button>
                                    <button
                                        v-if="
                                            ['pending', 'partial'].includes(
                                                p.status,
                                            )
                                        "
                                        type="button"
                                        class="text-red-600 text-xs"
                                        @click="cancelPayable(p.id)"
                                    >
                                        Cancel
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!payables.data?.length">
                                <td
                                    colspan="6"
                                    class="p-8 text-center text-slate-400 text-sm"
                                >
                                    No payables in this view
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 flex flex-wrap gap-2">
                    <Link
                        v-for="(link, i) in payables.links"
                        :key="i"
                        :href="link.url || '#'"
                        :class="
                            !link.url
                                ? 'pointer-events-none opacity-40'
                                : 'px-3 py-2'
                        "
                        v-html="link.label"
                    />
                </div>
            </section>
        </div>
        <form
            v-if="paying"
            class="card mt-6 space-y-3 max-w-2xl"
            @submit.prevent="recordPayment"
        >
            <h3 class="section-title">Payment to {{ paying.vendor_name }}</h3>
            <p>Balance due: ₹{{ fmt(paying.amount - paying.amount_paid) }}</p>
            <label
                >Amount (₹)<input
                    v-model="payment.amount"
                    type="number"
                    min="0.01"
                    :max="paying.amount - paying.amount_paid"
                    step="0.01"
                    required
                    class="field"
            /></label>
            <label
                >Payment date<input
                    v-model="payment.payment_date"
                    type="date"
                    required
                    class="field"
            /></label>
            <label
                >Payment account<select
                    v-model="payment.bank_account_id"
                    class="field"
                >
                    <option value="">Cash & Bank</option>
                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
                        {{ b.account_name }}
                    </option>
                </select></label
            >
            <label
                >Payment reference<input
                    v-model="payment.reference"
                    class="field"
            /></label>
            <p
                v-for="(error, key) in payment.errors"
                :key="key"
                class="text-red-700"
            >
                {{ error }}
            </p>
            <button class="btn-primary" :disabled="payment.processing">
                Record payment</button
            ><button
                type="button"
                class="btn-secondary ml-2"
                @click="paying = null"
            >
                Close
            </button>
        </form>
        <form
            v-if="cancelling"
            class="card mt-6 space-y-3 max-w-2xl"
            @submit.prevent="performCancel"
        >
            <h3 class="section-title">
                Cancel unpaid balance for {{ cancelling.vendor_name }}
            </h3>
            <label
                >Reason<textarea
                    v-model="cancellation.reason"
                    required
                    class="field"
                />
            </label>
            <p class="section-desc">
                Only the unpaid amount is reversed. Existing payments remain in
                history.
            </p>
            <button class="btn-primary" :disabled="cancellation.processing">
                Cancel unpaid balance</button
            ><button
                type="button"
                class="btn-secondary ml-2"
                @click="cancelling = null"
            >
                Keep bill
            </button>
        </form>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import SahodayaAdminLayout from "@/Layouts/SahodayaAdminLayout.vue";
import SearchableSelect from "@/Components/ui/SearchableSelect.vue";
import { formatCalendarDate } from "@/support/calendarDates.js";
import { useConfirm } from "@/composables/useConfirm";

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    payables: Object,
    expenseHeads: Array,
    totals: Object,
    filters: Object,
    academicYears: Array,
    bankAccounts: Array,
    paymentHistory: Object,
});

const statusFilter = ref(props.filters?.status ?? "open");
const yearFilter = ref(props.filters?.financial_year_id ?? "");

const expenseHeadOptions = computed(() =>
    (props.expenseHeads ?? []).map((h) => ({
        value: h.id,
        label: `${h.code} — ${h.name}`,
    })),
);

const { confirm } = useConfirm();

const createForm = useForm({
    submission_key: "",
    vendor_name: "",
    description: "",
    amount: "",
    due_date: "",
    incurred_date: "",
    expense_head_id: "",
    record_ledger: true,
});

function fmt(v) {
    return Number(v ?? 0).toLocaleString("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function applyFilter() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/finance/payables`,
        {
            status: statusFilter.value,
            financial_year_id: yearFilter.value,
        },
        { preserveScroll: true },
    );
}

function createPayable() {
    if (!createForm.submission_key)
        createForm.submission_key = crypto.randomUUID();
    createForm.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payables`, {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

const paying = ref(null),
    cancelling = ref(null);
const payment = useForm({
    submission_key: "",
    amount: "",
    payment_date: "",
    bank_account_id: "",
    reference: "",
});
const cancellation = useForm({ reason: "" });
function openPayment(p) {
    paying.value = p;
    payment.reset();
    payment.submission_key = crypto.randomUUID();
    payment.amount = p.amount - p.amount_paid;
    payment.payment_date = new Date().toLocaleDateString("en-CA");
}
async function recordPayment() {
    if (
        !(await confirm({
            message: `Record ₹${fmt(payment.amount)} paid to ${paying.value.vendor_name}?`,
            destructive: false,
        }))
    )
        return;
    payment.post(
        `/sahodaya-admin/${props.sahodaya.id}/finance/payables/${paying.value.id}/pay`,
        {
            preserveScroll: true,
            onSuccess: () => {
                paying.value = null;
            },
        },
    );
}
function cancelPayable(id) {
    cancelling.value = props.payables.data.find((p) => p.id === id);
    cancellation.reset();
}
function performCancel() {
    cancellation.post(
        `/sahodaya-admin/${props.sahodaya.id}/finance/payables/${cancelling.value.id}/cancel`,
        {
            preserveScroll: true,
            onSuccess: () => {
                cancelling.value = null;
            },
        },
    );
}
</script>
