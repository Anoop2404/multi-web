<template>
    <SchoolAdminLayout title="Food coupons" :school="school" :show-header-title="false">
        <PageHeader title="Food Coupons" eyebrow="Fest Hospitality"
            description="View and print meal coupons issued for your school's students, teachers, and team." />

        <form class="bg-white border rounded-xl p-4 flex flex-wrap gap-3 items-end mb-4 shadow-sm" @submit.prevent="applyFilter">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs font-semibold text-gray-600 block mb-1">Festival</label>
                <SearchableSelect v-model="eventFilter" :options="eventOptions" :all-option="true" all-label="All festivals" />
            </div>

            <div class="w-48">
                <label class="text-xs font-semibold text-gray-600 block mb-1">Meal Type</label>
                <select v-model="mealFilter" class="w-full text-xs rounded-lg border-gray-300 py-2">
                    <option value="">All Meals</option>
                    <option v-for="(label, key) in mealTypes" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Filter</button>
            <a v-if="eventFilter" :href="printUrl" target="_blank"
               class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Print 10-per-Sheet PDF
            </a>
        </form>

        <div class="card card--flush bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
            <table class="data-table w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                        <th class="py-3 px-3">Sl No</th>
                        <th class="py-3 px-3">Serialized Code</th>
                        <th class="py-3 px-3">QR Decoded Value</th>
                        <th class="py-3 px-3">Festival Event</th>
                        <th class="py-3 px-3">Meal Type</th>
                        <th class="py-3 px-3">Valid Date</th>
                        <th class="py-3 px-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(c, idx) in coupons" :key="c.id" class="hover:bg-slate-50/70 transition">
                        <td class="py-2.5 px-3 text-slate-400">{{ idx + 1 }}</td>
                        <td class="py-2.5 px-3">
                            <span class="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                {{ c.coupon_code }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="font-mono text-slate-600 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">
                                {{ c.qr_token }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 font-medium text-slate-800">{{ c.event?.title }}</td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide"
                                  :class="mealPillClass(c.meal_type)">
                                {{ c.meal_type }}
                            </span>
                        </td>
                        <td class="py-2.5 px-3 text-slate-600">{{ formatCalendarDate(c.valid_date) }}</td>
                        <td class="py-2.5 px-3">
                            <span class="status-pill text-[11px]" :class="couponStatusPillClass(c.status)">
                                {{ couponStatusLabel(c.status) }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!coupons.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">No food coupons issued for your school yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Coupons are printed 10 per A4 sheet with unique QR codes for entry and catering counters.
        </p>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { couponStatusLabel, couponStatusPillClass } from '@/support/foodBillStatus.js';

const props = defineProps({
    events: { type: Array, default: () => [] },
    coupons: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    mealTypes: { type: Object, default: () => ({}) },
});

const school = computed(() => usePage().props.school);
const eventFilter = ref(props.filters?.event_id ?? '');
const mealFilter = ref(props.filters?.meal_type ?? '');
const eventOptions = computed(() => (props.events ?? []).map(e => ({ value: e.id, label: e.title })));

const printUrl = computed(() => {
    if (!eventFilter.value) return '#';
    const params = new URLSearchParams();
    if (mealFilter.value) params.set('meal_type', mealFilter.value);
    const qs = params.toString();
    return `/school-admin/${school.value.id}/fest/${eventFilter.value}/food-coupons/print${qs ? '?' + qs : ''}`;
});

function applyFilter() {
    const params = {};
    if (eventFilter.value) params.event_id = eventFilter.value;
    if (mealFilter.value) params.meal_type = mealFilter.value;
    router.get(`/school-admin/${school.value.id}/food-coupons`, params, { preserveState: true });
}

function mealPillClass(meal) {
    const map = {
        breakfast: 'bg-amber-100 text-amber-800 border border-amber-300',
        lunch: 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        dinner: 'bg-indigo-100 text-indigo-800 border border-indigo-300',
        snacks: 'bg-pink-100 text-pink-800 border border-pink-300',
        tea: 'bg-orange-100 text-orange-800 border border-orange-300',
        other: 'bg-slate-100 text-slate-700 border border-slate-300',
    };
    return map[meal] || map.other;
}
</script>
