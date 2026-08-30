<template>
    <div>
        <FoodBillSummary :total="summary.total" :paid="summary.paid" :balance="summary.balance" class="mb-4 max-w-lg" />

        <!-- showOpenForm is only true on the Sahodaya side, which can open a bill for a
             school before they've ordered (e.g. a walk-in). The host-billing page has no
             equivalent form — bills there only ever appear once a school orders. -->
        <form v-if="showOpenForm" @submit.prevent="openBill" class="flex flex-wrap items-end gap-3 mb-6">
            <FormField label="Open/find bill for school" :error="openForm.errors.school_id">
                <template #default="{ id }">
                    <SearchableSelect :id="id" v-model="openForm.school_id" :options="schoolOptions"
                                       :all-option="true" all-label="— Select school —" />
                </template>
            </FormField>
            <button type="submit" class="btn-secondary text-sm" :disabled="openForm.processing || !openForm.school_id">Open</button>
        </form>

        <div class="filter-bar flex flex-wrap gap-3 items-center mb-4">
            <input v-model="search" type="search" class="field flex-1 min-w-[12rem] max-w-sm text-sm"
                   placeholder="Search by school…" autocomplete="off">
            <SearchableSelect v-model="statusFilter" class="w-auto"
                               :options="[{ value: 'open', label: 'Open' }, { value: 'settled', label: 'Settled' }]"
                               :all-option="true" all-label="All statuses" />
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" v-model="onlyBalanceDue"> Only with balance due
            </label>
            <button v-if="search || statusFilter || onlyBalanceDue" type="button" class="text-xs text-indigo-600 font-semibold" @click="clearFilters">Clear filters</button>
        </div>

        <div class="card card--flush">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School</th><th>Items</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in filteredBills" :key="b.id">
                        <td>{{ b.school_name }}</td>
                        <td>{{ b.items_count }}</td>
                        <td>₹{{ b.amount_total.toFixed(2) }}</td>
                        <td>₹{{ b.amount_paid.toFixed(2) }}</td>
                        <td :class="b.balance_due > 0 ? 'text-amber-700 font-semibold' : ''">₹{{ b.balance_due.toFixed(2) }}</td>
                        <td><FoodBillStatusBadge :status="b.status" /></td>
                        <td class="text-right">
                            <Link :href="`${basePath}/${b.id}`" class="text-xs font-semibold text-indigo-600">View</Link>
                        </td>
                    </tr>
                    <tr v-if="!bills.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No bills yet — they're created automatically once a school orders{{ showOpenForm ? ', or open one above.' : '.' }}</td>
                    </tr>
                    <tr v-else-if="!filteredBills.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No bills match your filters.</td>
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
