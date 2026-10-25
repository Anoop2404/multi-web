<template>
    <SahodayaAdminLayout
        title="Financial statements"
        :sahodaya="sahodaya"
        :publicUrl="publicUrl"
        :pendingPaymentsCount="pendingPaymentsCount"
        :show-header-title="false"
    >
        <PageHeader
            title="Financial statements"
            eyebrow="Finance"
            description="Net income, expenses, trial balance, cash book and account ledger."
            ><template #actions
                ><button class="btn-secondary" type="button" @click="print">
                    Print / Save PDF</button
                ><a :href="csvUrl" class="btn-secondary"
                    >Export selected report CSV</a
                ></template
            ></PageHeader
        >
        <form
            class="card flex flex-wrap gap-4 items-end mb-4"
            @submit.prevent="filter"
        >
            <label
                >Financial year<select v-model="year" class="field">
                    <option value="">All years</option>
                    <option
                        v-for="y in academicYears"
                        :key="y.id"
                        :value="y.id"
                    >
                        {{ y.label }}
                    </option>
                </select></label
            ><label
                >From<input v-model="from" type="date" class="field" /></label
            ><label
                >To / as of<input
                    v-model="to"
                    type="date"
                    class="field" /></label
            ><label
                >General-ledger account<select v-model="head" class="field">
                    <option value="">Select account</option>
                    <option v-for="h in heads" :key="h.id" :value="h.id">
                        {{ h.code }} · {{ h.name }}
                    </option>
                </select></label
            ><button class="btn-primary">Apply</button>
        </form>
        <p class="text-sm text-slate-500 mb-4">
            Income and cash movements use the date range. Trial balance and
            balance sheet include all movements up to the selected end date.
        </p>
        <div class="grid lg:grid-cols-2 gap-4 mb-6">
            <div class="card">
                <h3 class="section-title">Income & expenditure</h3>
                <p>Net income: ₹{{ money(incomeExpenditure.income) }}</p>
                <p>Net expenses: ₹{{ money(incomeExpenditure.expense) }}</p>
                <p class="font-bold mt-2">
                    Surplus / deficit: ₹{{ money(incomeExpenditure.surplus) }}
                </p>
            </div>
            <div class="card">
                <h3 class="section-title">Balance sheet</h3>
                <p>Assets: ₹{{ money(balanceSheet.assets) }}</p>
                <p>Liabilities: ₹{{ money(balanceSheet.liabilities) }}</p>
                <p class="font-bold mt-2">
                    Net assets / equity: ₹{{ money(balanceSheet.equity) }}
                </p>
            </div>
        </div>
        <nav class="flex flex-wrap gap-2 mb-4" aria-label="Financial reports">
            <button
                v-for="(label, key) in reports"
                :key="key"
                type="button"
                :class="report === key ? 'btn-primary' : 'btn-secondary'"
                @click="report = key"
            >
                {{ label }}
            </button>
        </nav>
        <section class="card overflow-x-auto">
            <h3 class="section-title">{{ reports[report] }}</h3>
            <table v-if="report === 'trialBalance'" class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance (Dr / Cr)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in trialBalance" :key="r.code">
                        <td>{{ r.code }}</td>
                        <td>{{ r.name }}</td>
                        <td>₹{{ money(r.debit) }}</td>
                        <td>₹{{ money(r.credit) }}</td>
                        <td>
                            ₹{{ money(Math.abs(r.balance)) }}
                            {{ r.balance < 0 ? "Cr" : "Dr" }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Total movements</th>
                        <th>₹{{ money(sum("debit")) }}</th>
                        <th>₹{{ money(sum("credit")) }}</th>
                        <th>
                            Difference ₹{{
                                money(sum("debit") - sum("credit"))
                            }}
                        </th>
                    </tr>
                </tfoot>
            </table>
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Debit / credit</th>
                        <th>Amount</th>
                        <th>Running balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id">
                        <td>{{ formatCalendarDate(r.transaction_date) }}</td>
                        <td>{{ r.description }}</td>
                        <td>{{ r.entry_type }}</td>
                        <td>₹{{ money(r.amount) }}</td>
                        <td>₹{{ money(r.running_balance) }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="5">
                            {{
                                report === "generalLedger" && !head
                                    ? "Select an account and apply filters."
                                    : "No entries in this range."
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </SahodayaAdminLayout>
</template>
<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import SahodayaAdminLayout from "@/Layouts/SahodayaAdminLayout.vue";
import PageHeader from "@/Components/ui/PageHeader.vue";
import { formatCalendarDate } from "@/support/calendarDates.js";
const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    trialBalance: Array,
    cashBook: Array,
    generalLedger: Array,
    incomeExpenditure: Object,
    balanceSheet: Object,
    heads: Array,
    academicYears: Array,
    filterFinancialYearId: Number,
    filterFrom: String,
    filterTo: String,
    filterHeadId: Number,
});
const year = ref(props.filterFinancialYearId ?? ""),
    from = ref(props.filterFrom ?? ""),
    to = ref(props.filterTo ?? ""),
    head = ref(props.filterHeadId ?? ""),
    report = ref("trialBalance");
const reports = {
    trialBalance: "Trial balance",
    cashBook: "Cash & bank book",
    generalLedger: "General ledger",
};
const rows = computed(() =>
    report.value === "cashBook" ? props.cashBook : props.generalLedger,
);
const params = () => ({
    financial_year_id: year.value,
    from: from.value,
    to: to.value,
    head_id: head.value,
});
const url = `/sahodaya-admin/${props.sahodaya.id}/finance/financial-statements`;
const csvUrl = computed(
    () =>
        `${url}?${new URLSearchParams({ ...params(), format: "csv", report: report.value })}`,
);
function filter() {
    router.get(url, params(), { preserveState: true });
}
function sum(key) {
    return props.trialBalance.reduce((v, r) => v + Number(r[key] ?? 0), 0);
}
function money(v) {
    return Number(v ?? 0).toLocaleString("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
function print() {
    window.print();
}
</script>
<style>
@media print {
    aside,
    nav,
    button,
    form,
    header {
        display: none !important;
    }
    main {
        margin: 0 !important;
    }
    body {
        background: white !important;
    }
    .card {
        box-shadow: none !important;
        break-inside: avoid;
    }
}
</style>
