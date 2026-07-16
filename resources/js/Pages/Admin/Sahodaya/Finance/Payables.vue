<template>
    <SahodayaAdminLayout title="Payables" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Accounts payable" eyebrow="Finance"
                    description="Track amounts the Sahodaya owes vendors, state bodies, or other parties — with due dates and ledger posting.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance`" class="btn-secondary text-sm">← Finance hub</Link>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-2 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs uppercase text-slate-500 font-semibold">Open balance</p>
                <p class="text-2xl font-bold text-red-800 mt-1">₹{{ fmt(totals.open_amount) }}</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xs uppercase text-slate-500 font-semibold">Overdue items</p>
                <p class="text-2xl font-bold text-amber-700 mt-1">{{ totals.overdue_count }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <section class="card space-y-3 lg:col-span-1">
                <h3 class="section-title">Record payable</h3>
                <p class="section-desc text-xs">Posts Dr expense / Cr ACC-PAYABLE when saved (unless you uncheck ledger).</p>
                <form @submit.prevent="createPayable" class="space-y-2">
                    <FormField label="Vendor / payee" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="createForm.vendor_name" class="field" required placeholder="State board, printer, venue…">
                        </template>
                    </FormField>
                    <FormField label="Description">
                        <template #default="{ id }">
                            <input :id="id" v-model="createForm.description" class="field" placeholder="Optional detail">
                        </template>
                    </FormField>
                    <FormField label="Amount (₹)" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="createForm.amount" type="number" min="0.01" step="0.01" class="field" required>
                        </template>
                    </FormField>
                    <div class="grid grid-cols-2 gap-2">
                        <FormField label="Incurred date">
                            <template #default="{ id }">
                                <input :id="id" v-model="createForm.incurred_date" type="date" class="field">
                            </template>
                        </FormField>
                        <FormField label="Due date">
                            <template #default="{ id }">
                                <input :id="id" v-model="createForm.due_date" type="date" class="field">
                            </template>
                        </FormField>
                    </div>
                    <FormField label="Expense head">
                        <template #default="{ id }">
                            <select :id="id" v-model="createForm.expense_head_id" class="field">
                                <option value="">Administrative (default)</option>
                                <option v-for="h in expenseHeads" :key="h.id" :value="h.id">{{ h.code }} — {{ h.name }}</option>
                            </select>
                        </template>
                    </FormField>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="createForm.record_ledger" class="mt-0.5">
                        Post to ledger now
                    </label>
                    <button type="submit" class="btn-primary w-full text-sm" :disabled="createForm.processing">Save payable</button>
                </form>
            </section>

            <section class="card card--flush overflow-hidden !p-0 lg:col-span-2">
                <div class="p-4 border-b border-slate-100 flex flex-wrap gap-2 items-center">
                    <h3 class="section-title !mb-0 mr-auto">Payables</h3>
                    <select v-model="statusFilter" @change="applyFilter" class="field text-sm max-w-[10rem]">
                        <option value="open">Open</option>
                        <option value="paid">Paid</option>
                        <option value="all">All</option>
                    </select>
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
                                    <p class="font-medium text-sm">{{ p.vendor_name }}</p>
                                    <p v-if="p.description" class="text-xs text-slate-500">{{ p.description }}</p>
                                </td>
                                <td class="text-xs">{{ formatCalendarDate(p.due_date) }}</td>
                                <td class="text-right font-mono text-sm">₹{{ fmt(p.amount) }}</td>
                                <td class="text-right font-mono text-sm">₹{{ fmt(p.amount - p.amount_paid) }}</td>
                                <td><span class="text-xs capitalize font-semibold">{{ p.status }}</span></td>
                                <td class="text-right whitespace-nowrap">
                                    <button v-if="p.status !== 'paid' && p.status !== 'cancelled'"
                                            type="button" class="text-emerald-700 text-xs font-semibold mr-2"
                                            @click="markPaid(p.id, p.amount - p.amount_paid)">Mark paid</button>
                                    <button v-if="p.status === 'pending'"
                                            type="button" class="text-red-600 text-xs"
                                            @click="cancelPayable(p.id)">Cancel</button>
                                </td>
                            </tr>
                            <tr v-if="!payables.data?.length">
                                <td colspan="6" class="p-8 text-center text-slate-400 text-sm">No payables in this view</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    payables: Object,
    expenseHeads: Array,
    totals: Object,
    filters: Object,
});

const statusFilter = ref(props.filters?.status ?? 'open');

const createForm = useForm({
    vendor_name: '',
    description: '',
    amount: '',
    due_date: '',
    incurred_date: '',
    expense_head_id: '',
    record_ledger: true,
});

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyFilter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/finance/payables`, {
        status: statusFilter.value,
        financial_year_id: props.filters?.financial_year_id,
    }, { preserveScroll: true });
}

function createPayable() {
    createForm.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payables`, {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function markPaid(id, balance) {
    if (!confirm(`Record payment of ₹${fmt(balance)}?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payables/${id}/pay`, {
        amount: balance,
    }, { preserveScroll: true });
}

function cancelPayable(id) {
    if (!confirm('Cancel this payable?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payables/${id}/cancel`, {}, { preserveScroll: true });
}
</script>
