<template>
    <SahodayaAdminLayout
        title="Accounts workspace"
        :sahodaya="sahodaya"
        :publicUrl="publicUrl"
        :pendingPaymentsCount="pendingPaymentsCount"
        :show-header-title="false"
    >
        <PageHeader
            title="Accounts workspace"
            eyebrow="Finance"
            description="Record income and expenses, review vouchers, track budgets and manage financial years."
        >
            <template #actions
                ><Link :href="`${base}/payables`" class="btn-secondary"
                    >Vendor bills</Link
                ><Link
                    :href="`${base}/bank-reconciliation`"
                    class="btn-secondary"
                    >Bank reconciliation</Link
                ><Link
                    :href="`${base}/financial-statements`"
                    class="btn-secondary"
                    >Reports</Link
                ></template
            >
        </PageHeader>
        <nav class="flex flex-wrap gap-2 mb-6" aria-label="Accounts sections">
            <button
                v-for="t in tabs"
                :key="t"
                type="button"
                @click="tab = t"
                :class="tab === t ? 'btn-primary' : 'btn-secondary'"
            >
                {{ t }}
            </button>
        </nav>
        <div
            v-if="Object.keys(action.errors).length"
            role="alert"
            class="card mb-4 text-red-700"
        >
            <p v-for="(e, k) in action.errors" :key="k">{{ e }}</p>
        </div>
        <section v-if="tab === 'Vouchers'" class="space-y-4">
            <form
                class="card flex flex-wrap gap-3 items-end"
                @submit.prevent="applyFilters"
            >
                <label
                    >Status<select v-model="filters.status" class="field">
                        <option value="">All statuses</option>
                        <option>pending</option>
                        <option>posted</option>
                        <option>rejected</option>
                        <option>reversed</option>
                    </select></label
                >
                <label
                    >Type<select v-model="filters.kind" class="field">
                        <option value="">All types</option>
                        <option v-for="k in kinds" :value="k" :key="k">
                            {{ kindLabels[k] ?? k }}
                        </option>
                    </select></label
                >
                <label
                    >Vendor<select v-model="filters.vendor_id" class="field">
                        <option value="">All vendors</option>
                        <option v-for="v in vendors" :value="v.id" :key="v.id">
                            {{ v.name }}
                        </option>
                    </select></label
                >
                <label
                    >Financial year<select
                        v-model="filters.financial_year_id"
                        class="field"
                    >
                        <option value="">All years</option>
                        <option v-for="y in years" :key="y.id" :value="y.id">
                            {{ y.label }}
                        </option>
                    </select></label
                >
                <label
                    >From<input
                        v-model="filters.from"
                        type="date"
                        class="field"
                /></label>
                <label
                    >To<input v-model="filters.to" type="date" class="field"
                /></label>
                <label
                    >Event<select v-model="filters.event_id" class="field">
                        <option value="">All events</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">
                            {{ e.title }}
                        </option>
                    </select></label
                >
                <label
                    >Region<input
                        v-model="filters.region"
                        class="field"
                        placeholder="Exact region name"
                /></label>
                <button class="btn-secondary">Filter</button
                ><button
                    type="button"
                    class="btn-secondary"
                    :disabled="action.processing"
                    @click="generate"
                >
                    Generate due recurring vouchers
                </button>
            </form>
            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Voucher / date</th>
                            <th>Payee / description</th>
                            <th>Type / status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in documents.data" :key="d.id">
                            <td>
                                #{{ d.id }}<br />{{
                                    formatCalendarDate(d.document_date)
                                }}
                            </td>
                            <td>
                                {{ d.vendor?.name ?? "—" }}
                                <p class="text-xs text-slate-500">
                                    {{ d.description }}
                                </p>
                                <span v-if="d.region" class="text-xs">{{
                                    d.region
                                }}</span>
                                <p
                                    v-if="d.review_reason"
                                    class="text-xs text-red-700"
                                >
                                    {{ d.review_reason }}
                                </p>
                            </td>
                            <td>
                                {{ kindLabels[d.kind] ?? d.kind }}<br /><span
                                    class="font-semibold"
                                    >{{ d.status }}</span
                                >
                            </td>
                            <td class="whitespace-nowrap">
                                ₹{{ money(d.amount) }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        :href="`${base}/documents/${d.id}/voucher`"
                                        target="_blank"
                                        class="text-indigo-700"
                                        >Print voucher</a
                                    ><a
                                        v-if="d.attachment_path"
                                        :href="`${base}/documents/${d.id}/attachment`"
                                        class="text-indigo-700"
                                        >Bill</a
                                    ><button
                                        v-if="d.status === 'pending'"
                                        type="button"
                                        :disabled="action.processing"
                                        class="text-emerald-700"
                                        @click="review(d, 'approve')"
                                    >
                                        Approve & post</button
                                    ><button
                                        v-if="d.status === 'pending'"
                                        type="button"
                                        :disabled="action.processing"
                                        class="text-red-700"
                                        @click="
                                            rejecting = d;
                                            reviewChoice = 'reject';
                                        "
                                    >
                                        Reject</button
                                    ><button
                                        v-if="d.status === 'posted'"
                                        type="button"
                                        :disabled="action.processing"
                                        class="text-red-700"
                                        @click="
                                            rejecting = d;
                                            reviewChoice = 'reverse';
                                        "
                                    >
                                        Reverse
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!documents.data.length">
                            <td
                                colspan="5"
                                class="text-center py-10 text-slate-500"
                            >
                                No vouchers match. Use New voucher to record an
                                expense or income.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex flex-wrap gap-2 mt-4">
                    <Link
                        v-for="(l, i) in documents.links"
                        :key="i"
                        :href="l.url || '#'"
                        :class="[
                            'px-3 py-2 rounded',
                            l.active ? 'bg-indigo-100' : '',
                            !l.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="l.label"
                    />
                </div>
            </div>
            <form
                v-if="rejecting"
                class="card space-y-3"
                @submit.prevent="review(rejecting, reviewChoice)"
            >
                <h3 class="section-title">
                    {{ reviewChoice === "reverse" ? "Reverse" : "Reject" }}
                    voucher #{{ rejecting.id }}
                </h3>
                <label
                    >Reason<textarea
                        v-model="reviewReason"
                        required
                        class="field"
                        maxlength="1000"
                    /></label
                ><button class="btn-primary" :disabled="action.processing">
                    {{
                        reviewChoice === "reverse"
                            ? "Post reversal"
                            : "Reject voucher"
                    }}</button
                ><button
                    class="btn-secondary ml-2"
                    type="button"
                    @click="rejecting = null"
                >
                    Cancel
                </button>
            </form>
        </section>
        <form
            v-if="tab === 'New voucher'"
            class="card max-w-4xl space-y-4"
            @submit.prevent="saveDocument"
        >
            <h3 class="section-title">New voucher</h3>
            <p class="section-desc">
                Save for review first. Approval posts the balanced journal.
                Advances remain assets until settled with expense bills.
            </p>
            <div class="grid sm:grid-cols-2 gap-4">
                <label
                    >Type<select v-model="form.kind" class="field">
                        <option v-for="k in kinds" :value="k" :key="k">
                            {{ kindLabels[k] ?? k }}
                        </option>
                    </select></label
                >
                <label
                    >Date<input
                        v-model="form.document_date"
                        type="date"
                        required
                        class="field"
                /></label>
                <label
                    >Vendor / payee<select
                        v-model="form.vendor_id"
                        class="field"
                    >
                        <option value="">Choose vendor (optional)</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">
                            {{ v.name }}
                        </option>
                    </select></label
                >
                <label
                    >Amount (₹)<input
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        class="field"
                /></label>
                <label
                    v-if="
                        ['expense', 'income', 'settlement'].includes(form.kind)
                    "
                    >Account head<select
                        v-model="form.account_head_id"
                        class="field"
                        required
                    >
                        <option value="">Choose account</option>
                        <option
                            v-for="h in voucherHeads"
                            :key="h.id"
                            :value="h.id"
                        >
                            {{ h.name }}
                        </option>
                    </select></label
                >
                <label v-if="form.kind !== 'settlement'"
                    >{{
                        ["income", "advance_return"].includes(form.kind)
                            ? "Receive into"
                            : "Pay from"
                    }}<select v-model="form.bank_account_id" class="field">
                        <option value="">Cash & Bank</option>
                        <option v-for="b in banks" :key="b.id" :value="b.id">
                            {{ b.account_name }}
                        </option>
                    </select></label
                >
                <label v-if="form.kind === 'transfer'"
                    >Transfer to<select
                        v-model="form.destination_bank_id"
                        class="field"
                    >
                        <option value="">Cash & Bank</option>
                        <option v-for="b in banks" :key="b.id" :value="b.id">
                            {{ b.account_name }}
                        </option>
                    </select></label
                >
                <label
                    v-if="['settlement', 'advance_return'].includes(form.kind)"
                    >Advance to settle<select
                        v-model="form.parent_id"
                        required
                        class="field"
                    >
                        <option value="">Choose posted advance</option>
                        <option v-for="a in advances" :key="a.id" :value="a.id">
                            #{{ a.id }} · {{ a.description }} · ₹{{
                                money(a.amount)
                            }}
                        </option>
                    </select></label
                >
                <label
                    >Event<select v-model="form.event_id" class="field">
                        <option value="">General / no event</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">
                            {{ e.title }}
                        </option>
                    </select></label
                >
                <label
                    >Region<input
                        v-model="form.region"
                        class="field"
                        maxlength="100"
                /></label>
                <label
                    >Payment / bill reference<input
                        v-model="form.reference"
                        class="field"
                        maxlength="255"
                /></label>
                <label
                    >Bill attachment (PDF/JPG/PNG, up to 10 MB)<input
                        type="file"
                        accept=".pdf,.png,.jpg,.jpeg"
                        class="field"
                        @change="form.attachment = $event.target.files[0]"
                /></label>
            </div>
            <label class="block"
                >Description<textarea
                    v-model="form.description"
                    required
                    maxlength="2000"
                    class="field"
                />
            </label>
            <div
                v-if="['expense', 'income'].includes(form.kind)"
                class="flex flex-wrap gap-4 items-center"
            >
                <label
                    ><input v-model="form.recurring" type="checkbox" /> Repeat
                    monthly</label
                ><label v-if="form.recurring"
                    >Next due<input
                        v-model="form.next_due"
                        type="date"
                        required
                        class="field"
                /></label>
            </div>
            <p
                v-for="(e, k) in form.errors"
                :key="k"
                role="alert"
                class="text-red-700 text-sm"
            >
                {{ e }}
            </p>
            <button class="btn-primary" :disabled="form.processing">
                Submit for approval
            </button>
        </form>
        <section v-if="tab === 'Vendors'" class="grid lg:grid-cols-3 gap-4">
            <form
                class="card space-y-3"
                @submit.prevent="vendor.post(`${base}/vendors`)"
            >
                <h3 class="section-title">Vendor / payee</h3>
                <label
                    >Name<input
                        v-model="vendor.name"
                        required
                        class="field" /></label
                ><label
                    >Email<input
                        v-model="vendor.email"
                        type="email"
                        class="field" /></label
                ><label
                    >Phone<input v-model="vendor.phone" class="field" /></label
                ><label
                    >Address<textarea v-model="vendor.address" class="field" />
                </label>
                <p
                    v-for="(e, k) in vendor.errors"
                    :key="k"
                    class="text-red-700"
                >
                    {{ e }}
                </p>
                <button :disabled="vendor.processing" class="btn-primary">
                    Save vendor
                </button>
            </form>
            <div class="card lg:col-span-2">
                <h3 class="section-title">Vendors</h3>
                <p v-if="!vendors.length">No vendors yet.</p>
                <div v-for="v in vendors" :key="v.id" class="border-b py-3">
                    <p class="font-semibold">{{ v.name }}</p>
                    <p>{{ v.email }} {{ v.phone }}</p>
                    <button
                        class="text-indigo-700"
                        type="button"
                        @click="
                            filters.vendor_id = v.id;
                            tab = 'Vouchers';
                            applyFilters();
                        "
                    >
                        View voucher history
                    </button>
                </div>
            </div>
        </section>
        <section v-if="tab === 'Budgets'" class="space-y-4">
            <form
                class="card grid md:grid-cols-4 gap-4 items-end"
                @submit.prevent="budget.post(`${base}/budgets`)"
            >
                <label
                    >Financial year<select
                        v-model="budget.financial_year_id"
                        required
                        class="field"
                    >
                        <option v-for="y in years" :key="y.id" :value="y.id">
                            {{ y.label }}
                        </option>
                    </select></label
                ><label
                    >Expense account<select
                        v-model="budget.account_head_id"
                        required
                        class="field"
                    >
                        <option
                            v-for="h in heads.filter(
                                (h) => h.type === 'expense',
                            )"
                            :key="h.id"
                            :value="h.id"
                        >
                            {{ h.name }}
                        </option>
                    </select></label
                ><label
                    >Event<select v-model="budget.event_id" class="field">
                        <option value="">All events / general</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">
                            {{ e.title }}
                        </option>
                    </select></label
                ><label
                    >Budget (₹)<input
                        v-model="budget.amount"
                        required
                        type="number"
                        min="0"
                        step="0.01"
                        class="field" /></label
                ><button class="btn-primary" :disabled="budget.processing">
                    Save budget
                </button>
                <p
                    v-for="(e, k) in budget.errors"
                    :key="k"
                    class="text-red-700"
                >
                    {{ e }}
                </p>
            </form>
            <div class="card overflow-x-auto">
                <p class="section-desc">
                    Event budgets include posted vouchers allocated to that
                    event. General budgets include all journal movements for the
                    account.
                </p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Year / account / event</th>
                            <th>Budget</th>
                            <th>Actual</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in budgets" :key="b.id">
                            <td>
                                {{
                                    years.find(
                                        (y) => y.id === b.financial_year_id,
                                    )?.label
                                }}
                                ·
                                {{
                                    heads.find(
                                        (h) => h.id === b.account_head_id,
                                    )?.name
                                }}
                                ·
                                {{
                                    events.find((e) => e.id === b.event_id)
                                        ?.title ?? "General"
                                }}
                            </td>
                            <td>₹{{ money(b.amount) }}</td>
                            <td>₹{{ money(b.actual) }}</td>
                            <td :class="b.remaining < 0 ? 'text-red-700' : ''">
                                ₹{{ money(b.remaining) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <form
            v-if="tab === 'Bank accounts'"
            class="card max-w-2xl space-y-4"
            @submit.prevent="bank.post(`${base}/banks`)"
        >
            <h3 class="section-title">Add bank account</h3>
            <p class="section-desc">
                Each bank gets its own asset ledger account. Use Transfer
                vouchers to move money between cash and banks.
            </p>
            <label
                >Account name<input
                    v-model="bank.account_name"
                    required
                    class="field" /></label
            ><label
                >Bank name<input
                    v-model="bank.bank_name"
                    required
                    class="field" /></label
            ><label
                >Account number<input
                    v-model="bank.account_no"
                    required
                    class="field"
                    autocomplete="off" /></label
            ><label>IFSC<input v-model="bank.ifsc" class="field" /></label>
            <p v-for="(e, k) in bank.errors" :key="k" class="text-red-700">
                {{ e }}
            </p>
            <button :disabled="bank.processing" class="btn-primary">
                Add account
            </button>
            <p v-for="b in banks" :key="b.id">
                {{ b.account_name }} · {{ b.bank_name }}
            </p>
        </form>
        <section v-if="tab === 'Year closing'" class="space-y-4">
            <form class="card max-w-3xl space-y-4" @submit.prevent="closeYear">
                <h3 class="section-title">Close financial year</h3>
                <p class="section-desc">
                    Closing locks this Sahodaya’s year against new postings.
                    Optionally carry asset and liability balances into an empty
                    later year. Review reports and all pending vouchers first.
                </p>
                <label
                    >Year to close<select
                        v-model="closing.financial_year_id"
                        required
                        class="field"
                    >
                        <option v-for="y in years" :key="y.id" :value="y.id">
                            {{ y.label }}
                        </option>
                    </select></label
                ><label
                    >Carry forward into<select
                        v-model="closing.carry_to_year_id"
                        class="field"
                    >
                        <option value="">Do not carry forward</option>
                        <option v-for="y in years" :key="y.id" :value="y.id">
                            {{ y.label }}
                        </option>
                    </select></label
                ><label
                    >Closing note<textarea
                        v-model="closing.reason"
                        required
                        class="field"
                    />
                </label>
                <p
                    v-for="(e, k) in closing.errors"
                    :key="k"
                    class="text-red-700"
                >
                    {{ e }}
                </p>
                <button :disabled="closing.processing" class="btn-primary">
                    Review and close year
                </button>
            </form>
            <div class="card">
                <h3 class="section-title">Closed periods</h3>
                <p v-for="p in periodLocks" :key="p.id">
                    {{ years.find((y) => y.id === p.financial_year_id)?.label }}
                    · {{ p.reason }}
                </p>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>
<script setup>
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import SahodayaAdminLayout from "@/Layouts/SahodayaAdminLayout.vue";
import PageHeader from "@/Components/ui/PageHeader.vue";
import { formatCalendarDate } from "@/support/calendarDates.js";
import { useConfirm } from "@/composables/useConfirm";
const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    documents: Object,
    vendors: Array,
    heads: Array,
    banks: Array,
    years: Array,
    events: Array,
    budgets: Array,
    periodLocks: Array,
    advances: Array,
    filters: Object,
});
const base = `/sahodaya-admin/${props.sahodaya.id}/finance`;
const { confirm } = useConfirm();
const tabs = [
    "Vouchers",
    "New voucher",
    "Vendors",
    "Budgets",
    "Bank accounts",
    "Year closing",
];
const tab = ref("Vouchers");
const kindLabels = {
    expense: "Expense / reimbursement",
    income: "Income",
    transfer: "Bank / cash transfer",
    advance: "Advance issued",
    settlement: "Advance expense settlement",
    advance_return: "Unused advance returned",
};
const kinds = [
    "expense",
    "income",
    "transfer",
    "advance",
    "settlement",
    "advance_return",
];
const key = () => crypto.randomUUID();
const today = new Date().toLocaleDateString("en-CA");
const form = useForm({
    submission_key: "",
    kind: "expense",
    vendor_id: "",
    account_head_id: "",
    bank_account_id: "",
    destination_bank_id: "",
    event_id: "",
    parent_id: "",
    region: "",
    amount: "",
    document_date: today,
    reference: "",
    description: "",
    attachment: null,
    recurring: false,
    next_due: "",
});
const action = useForm({ action: "", reason: "" });
const rejecting = ref(null),
    reviewReason = ref(""),
    reviewChoice = ref("reject");
const vendor = useForm({ name: "", email: "", phone: "", address: "" }),
    bank = useForm({
        account_name: "",
        bank_name: "",
        account_no: "",
        ifsc: "",
    });
const budget = useForm({
    financial_year_id: props.years.find((y) => y.is_current)?.id,
    account_head_id: "",
    event_id: "",
    amount: "",
});
const closing = useForm({
    financial_year_id: "",
    carry_to_year_id: "",
    reason: "",
});
const filters = ref({
    kind: "",
    status: "",
    vendor_id: "",
    financial_year_id: "",
    from: "",
    to: "",
    event_id: "",
    region: "",
    ...props.filters,
});
const voucherHeads = computed(() =>
    props.heads.filter(
        (h) => h.type === (form.kind === "income" ? "income" : "expense"),
    ),
);
function money(v) {
    return Number(v ?? 0).toLocaleString("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
function applyFilters() {
    router.get(`${base}/workspace`, filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
}
function saveDocument() {
    if (!form.submission_key) form.submission_key = key();
    form.post(`${base}/documents`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            tab.value = "Vouchers";
        },
    });
}
async function review(d, choice) {
    if (
        choice === "approve" &&
        !(await confirm({
            message: `Post voucher #${d.id} for ₹${money(d.amount)}?`,
            destructive: false,
        }))
    )
        return;
    action.action = choice;
    action.reason = reviewReason.value;
    action.post(`${base}/documents/${d.id}/review`, {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            reviewReason.value = "";
        },
    });
}
function generate() {
    action.post(`${base}/recurring/generate`, { preserveScroll: true });
}
async function closeYear() {
    if (
        await confirm({
            message:
                "Close and lock this financial year? Review statements before continuing.",
            destructive: true,
        })
    )
        closing.post(`${base}/years/close`, { preserveScroll: true });
}
</script>
