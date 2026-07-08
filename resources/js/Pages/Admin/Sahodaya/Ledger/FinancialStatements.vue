<template>
    <SahodayaAdminLayout title="Financial statements" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Financial statements" eyebrow="Finance"
                    description="RPT-FIN-005–007 · trial balance, income & expenditure, balance sheet." />

        <div class="grid lg:grid-cols-2 gap-4 mb-6">
            <div class="card">
                <h3 class="font-semibold mb-2">Income & Expenditure</h3>
                <p>Income: ₹{{ fmt(incomeExpenditure.income) }}</p>
                <p>Expense: ₹{{ fmt(incomeExpenditure.expense) }}</p>
                <p class="font-bold mt-2">Surplus: ₹{{ fmt(incomeExpenditure.surplus) }}</p>
            </div>
            <div class="card">
                <h3 class="font-semibold mb-2">Balance Sheet</h3>
                <p>Assets: ₹{{ fmt(balanceSheet.assets) }}</p>
                <p>Liabilities: ₹{{ fmt(balanceSheet.liabilities) }}</p>
                <p class="font-bold mt-2">Equity: ₹{{ fmt(balanceSheet.equity) }}</p>
            </div>
        </div>

        <section v-if="monthlyIncome?.length" class="card mb-6">
            <h3 class="section-title mb-3">Monthly income trend (RPT-FIN-015)</h3>
            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                <div v-for="m in monthlyIncome" :key="m.month" class="rounded-lg border border-slate-100 p-2 text-center">
                    <p class="text-[10px] uppercase text-slate-400">{{ m.month }}</p>
                    <p class="text-sm font-bold text-[#0f3d7a]">₹{{ fmt(m.income) }}</p>
                    <p class="text-[10px] text-slate-500">Exp ₹{{ fmt(m.expense) }}</p>
                </div>
            </div>
        </section>

        <div class="card overflow-x-auto">
            <h3 class="font-semibold mb-2">Trial Balance</h3>
            <table class="data-table text-sm">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Debit</th><th>Credit</th><th>Balance</th></tr>
                </thead>
                <tbody>
                    <tr v-for="r in trialBalance" :key="r.code">
                        <td>{{ r.code }}</td>
                        <td>{{ r.name }}</td>
                        <td>{{ r.debit }}</td>
                        <td>{{ r.credit }}</td>
                        <td>{{ r.balance }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    trialBalance: Array,
    incomeExpenditure: Object,
    balanceSheet: Object,
    monthlyIncome: { type: Array, default: () => [] },
});

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
