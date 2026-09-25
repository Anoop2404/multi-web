<template>
    <div class="space-y-6">
        <FoodBillSummary :total="summary.total" :paid="summary.paid" :balance="summary.balance" class="max-w-xl" />

        <!-- showOpenForm is only true on the Sahodaya side, which can open a bill for a
             school before they've ordered (e.g. a walk-in). The host-billing page has no
             equivalent form — bills there only ever appear once a school orders. -->
        <div v-if="showOpenForm" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700 mb-3 flex items-center gap-1.5">
                <span>➕</span> Open or Find Bill for School
            </h4>
            <form @submit.prevent="openBill" class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[16rem]">
                    <SearchableSelect v-model="openForm.school_id" :options="schoolOptions"
                                      :all-option="true" all-label="— Select school —" />
                </div>
                <button type="submit" class="btn-primary text-xs font-bold px-4 py-2" :disabled="openForm.processing || !openForm.school_id">
                    {{ openForm.processing ? 'Opening…' : 'Open Bill' }}
                </button>
            </form>
            <p v-if="openForm.errors.school_id" class="text-xs text-rose-600 mt-2">{{ openForm.errors.school_id }}</p>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap gap-3 items-center justify-between bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-sm">
            <div class="flex flex-wrap gap-2.5 items-center flex-1">
                <input v-model="search" type="search" class="field text-xs min-w-[14rem] max-w-sm"
                       placeholder="Search school name…" autocomplete="off">
                <SearchableSelect v-model="statusFilter" class="w-auto text-xs"
                                   :options="[{ value: 'open', label: 'Open' }, { value: 'settled', label: 'Settled' }]"
                                   :all-option="true" all-label="All Statuses" />
                <label class="flex items-center gap-2 text-xs font-medium text-slate-700 bg-slate-50 px-3 py-2 rounded-xl border border-slate-200/60 cursor-pointer">
                    <input type="checkbox" v-model="onlyBalanceDue" class="text-indigo-600 rounded">
                    Only with balance due
                </label>
            </div>
            <button v-if="search || statusFilter || onlyBalanceDue" type="button"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                    @click="clearFilters">
                Clear filters
            </button>
        </div>

        <!-- Bills Table -->
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
            <table class="data-table text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="py-3">School</th>
                        <th class="py-3">Items</th>
                        <th class="py-3">Total Billed</th>
                        <th class="py-3">Paid</th>
                        <th class="py-3">Balance Due</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in filteredBills" :key="b.id" class="hover:bg-slate-50/70 transition">
                        <td class="font-bold text-slate-900 py-3">{{ b.school_name }}</td>
                        <td class="text-slate-600 py-3">{{ b.items_count }} item{{ b.items_count === 1 ? '' : 's' }}</td>
                        <td class="font-bold text-slate-900 py-3">₹{{ Number(b.amount_total).toFixed(2) }}</td>
                        <td class="font-bold text-emerald-700 py-3">₹{{ Number(b.amount_paid).toFixed(2) }}</td>
                        <td class="py-3" :class="Number(b.balance_due) > 0 ? 'text-rose-700 font-extrabold' : 'text-slate-600 font-medium'">
                            ₹{{ Number(b.balance_due).toFixed(2) }}
                        </td>
                        <td class="py-3"><FoodBillStatusBadge :status="b.status" /></td>
                        <td class="py-3 text-right">
                            <Link :href="`${basePath}/${b.id}`"
                                  class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition">
                                View Details →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!bills.length">
                        <td colspan="7" class="p-12 text-center text-slate-400">
                            <span class="text-3xl block mb-2">📋</span>
                            No food bills created yet — they are created automatically once a school places an order{{ showOpenForm ? ', or you can open one manually above.' : '.' }}
                        </td>
                    </tr>
                    <tr v-else-if="!filteredBills.length">
                        <td colspan="7" class="p-12 text-center text-slate-400">
                            No bills match your current filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import FoodBillSummary from '@/Components/food/FoodBillSummary.vue';
import FoodBillStatusBadge from '@/Components/food/FoodBillStatusBadge.vue';

const props = defineProps({
    bills: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, paid: 0, balance: 0 }) },
    basePath: { type: String, required: true },
    showOpenForm: { type: Boolean, default: false },
    schoolOptions: { type: Array, default: () => [] },
});

const openForm = useForm({ school_id: '' });
function openBill() {
    openForm.post(props.basePath);
}

const search = ref('');
const statusFilter = ref('');
const onlyBalanceDue = ref(false);

const filteredBills = computed(() => props.bills.filter((b) => {
    if (search.value.trim() && !b.school_name.toLowerCase().includes(search.value.trim().toLowerCase())) return false;
    if (statusFilter.value && b.status !== statusFilter.value) return false;
    if (onlyBalanceDue.value && b.balance_due <= 0) return false;
    return true;
}));

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
    onlyBalanceDue.value = false;
}
</script>
