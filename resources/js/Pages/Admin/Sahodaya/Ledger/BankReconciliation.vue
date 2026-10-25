<template>
    <SahodayaAdminLayout
        title="Bank reconciliation"
        :sahodaya="sahodaya"
        :publicUrl="publicUrl"
        :pendingPaymentsCount="pendingPaymentsCount"
        :show-header-title="false"
    >
        <PageHeader
            title="Bank reconciliation"
            eyebrow="Finance"
            description="Import bank statements, review suggestions and reconcile cash/bank entries."
            ><template #actions
                ><Link :href="`${base}/workspace`" class="btn-secondary"
                    >Manage bank accounts</Link
                ></template
            ></PageHeader
        >
        <form
            class="card flex flex-wrap gap-4 items-end mb-4"
            @submit.prevent="filter"
        >
            <label
                >Bank<select v-model="bank" class="field">
                    <option value="">All cash & banks</option>
                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
                        {{ b.account_name }}
                    </option>
                </select></label
            ><label
                >Status<select v-model="status" class="field">
                    <option value="all">All entries</option>
                    <option value="open">Unreconciled</option>
                    <option value="reconciled">Reconciled</option>
                </select></label
            ><button class="btn-secondary">Apply</button>
        </form>
        <div class="grid sm:grid-cols-3 gap-4 mb-4">
            <div class="card">Receipts ₹{{ money(totals.debits) }}</div>
            <div class="card">Payments ₹{{ money(totals.credits) }}</div>
            <div class="card">
                {{ totals.unreconciled }} unreconciled entries
            </div>
        </div>
        <form class="card space-y-3 mb-4" @submit.prevent="upload">
            <h3 class="section-title">Import statement CSV</h3>
            <p class="section-desc">
                Columns: date,description,amount. Use YYYY-MM-DD. Deposits are
                positive; withdrawals negative. Up to 5,000 rows / 2 MB.
            </p>
            <label
                >Statement bank<select
                    v-model="importForm.bank_account_id"
                    required
                    class="field max-w-sm"
                >
                    <option value="">Choose bank</option>
                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
                        {{ b.account_name }}
                    </option>
                </select></label
            ><label class="block"
                >CSV file<input
                    type="file"
                    accept=".csv"
                    required
                    class="field max-w-sm"
                    @change="importForm.statement = $event.target.files[0]"
            /></label>
            <p
                v-for="(e, k) in importForm.errors"
                :key="k"
                class="text-red-700"
            >
                {{ e }}
            </p>
            <button class="btn-primary" :disabled="importForm.processing">
                Import for review
            </button>
        </form>
        <p
            v-for="(e, k) in action.errors"
            :key="k"
            class="text-red-700"
            role="alert"
        >
            {{ e }}
        </p>
        <section class="card overflow-x-auto mb-4">
            <h3 class="section-title">Statement matching</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Signed amount</th>
                        <th>Match</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in statementLines.data" :key="l.id">
                        <td>{{ formatCalendarDate(l.transaction_date) }}</td>
                        <td>{{ l.description }}</td>
                        <td>₹{{ money(l.amount) }}</td>
                        <td>
                            <span v-if="l.ledger_transaction_id"
                                >Matched to #{{ l.ledger_transaction_id }}</span
                            >
                            <div v-else>
                                <label
                                    >Suggested journal entry<select
                                        v-model="selected[l.id]"
                                        class="field"
                                    >
                                        <option value="">Choose a match</option>
                                        <option
                                            v-for="t in l.suggestions"
                                            :key="t.id"
                                            :value="t.id"
                                        >
                                            #{{ t.id }} ·
                                            {{
                                                formatCalendarDate(
                                                    t.transaction_date,
                                                )
                                            }}
                                            · {{ t.description }}
                                        </option>
                                    </select></label
                                ><button
                                    type="button"
                                    class="btn-secondary text-sm"
                                    :disabled="
                                        !selected[l.id] || action.processing
                                    "
                                    @click="match(l.id)"
                                >
                                    Confirm match
                                </button>
                                <p
                                    v-if="!l.suggestions.length"
                                    class="text-sm text-slate-500"
                                >
                                    No equal-amount entry within three days.
                                    Check your ledger before recording a missing
                                    voucher.
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!statementLines.data.length">
                        <td colspan="4">No statement lines imported.</td>
                    </tr>
                </tbody>
            </table>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="(l, i) in statementLines.links"
                    :key="i"
                    :href="l.url || '#'"
                    :class="!l.url ? 'pointer-events-none opacity-40' : 'p-2'"
                    v-html="l.label"
                />
            </div>
        </section>
        <div class="card overflow-x-auto">
            <h3 class="section-title">Cash & bank ledger</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account / description</th>
                        <th>Debit / credit</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in transactions.data" :key="t.id">
                        <td>{{ formatCalendarDate(t.transaction_date) }}</td>
                        <td>
                            {{ t.account_head?.name }}
                            <p class="text-xs">{{ t.description }}</p>
                        </td>
                        <td>{{ t.entry_type }}</td>
                        <td>₹{{ money(t.amount) }}</td>
                        <td>{{ t.reconciled_at ? "Reconciled" : "Open" }}</td>
                        <td>
                            <button
                                type="button"
                                class="text-indigo-700"
                                :disabled="action.processing"
                                @click="toggle(t)"
                            >
                                {{
                                    t.reconciled_at
                                        ? "Clear match"
                                        : "Mark reconciled"
                                }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!transactions.data.length">
                        <td colspan="6">No entries match these filters.</td>
                    </tr>
                </tbody>
            </table>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="(l, i) in transactions.links"
                    :key="i"
                    :href="l.url || '#'"
                    :class="!l.url ? 'pointer-events-none opacity-40' : 'p-2'"
                    v-html="l.label"
                />
            </div>
        </div>
    </SahodayaAdminLayout>
</template>
<script setup>
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import SahodayaAdminLayout from "@/Layouts/SahodayaAdminLayout.vue";
import PageHeader from "@/Components/ui/PageHeader.vue";
import { formatCalendarDate } from "@/support/calendarDates.js";
const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    transactions: Object,
    bankAccounts: Array,
    filterBankId: Number,
    filterStatus: String,
    statementLines: Object,
    totals: Object,
});
const base = `/sahodaya-admin/${props.sahodaya.id}/finance`;
const bank = ref(props.filterBankId ?? ""),
    status = ref(props.filterStatus),
    selected = ref({});
const importForm = useForm({
        bank_account_id: props.filterBankId ?? "",
        statement: null,
    }),
    action = useForm({
        ledger_transaction_id: null,
        reconciled: false,
        bank_account_id: null,
    });
function money(v) {
    return Number(v ?? 0).toLocaleString("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
function filter() {
    router.get(
        `${base}/bank-reconciliation`,
        { bank_account_id: bank.value, status: status.value },
        { preserveScroll: true },
    );
}
function upload() {
    importForm.post(`${base}/bank-reconciliation/import`, {
        preserveScroll: true,
    });
}
function match(id) {
    action.ledger_transaction_id = selected.value[id];
    action.post(`${base}/bank-reconciliation/lines/${id}/match`, {
        preserveScroll: true,
    });
}
function toggle(t) {
    action.reconciled = !t.reconciled_at;
    action.bank_account_id = t.bank_account_id ?? (bank.value || null);
    action.post(`${base}/bank-reconciliation/${t.id}/reconcile`, {
        preserveScroll: true,
    });
}
</script>
