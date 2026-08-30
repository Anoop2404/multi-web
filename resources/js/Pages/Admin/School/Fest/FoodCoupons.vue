<template>
    <SchoolAdminLayout title="Food coupons" :school="school" :show-header-title="false">
        <PageHeader title="Food coupons" eyebrow="Programs"
            description="Fest programs, exams, training, and Sahodaya circulars." />


        <form class="bg-white border rounded-xl p-4 flex flex-wrap gap-2 items-end mb-4" @submit.prevent="applyFilter">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500 block mb-1">Festival</label>
                <SearchableSelect v-model="eventFilter" :options="eventOptions" :all-option="true" all-label="All festivals" />
            </div>
            <button class="btn-primary">Filter</button>
            <a v-if="eventFilter" :href="printUrl" target="_blank"
               class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium">Print issued coupons</a>
        </form>

        <div class="card card--flush">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Event</th>
                        <th>Meal</th>
                        <th>Valid date</th>
                        <th>Heads</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in coupons" :key="c.id">
                        <td class="font-mono font-semibold">{{ c.coupon_code }}</td>
                        <td>{{ c.event?.title }}</td>
                        <td class="capitalize">{{ c.meal_type }}</td>
                        <td>{{ formatCalendarDate(c.valid_date) }}</td>
                        <td>{{ c.head_count }}</td>
                        <td>
                            <span class="status-pill" :class="couponStatusPillClass(c.status)">{{ couponStatusLabel(c.status) }}</span>
                        </td>
                    </tr>
                    <tr v-if="!coupons.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No food coupons issued for your school yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-gray-500">Coupons are issued by the Sahodaya fest team from confirmed catering orders.</p>
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
    events: Array,
    coupons: Array,
    filters: Object,
});

const school = computed(() => usePage().props.school);
const eventFilter = ref(props.filters?.event_id ?? '');
const eventOptions = computed(() => (props.events ?? []).map(e => ({ value: e.id, label: e.title })));

const printUrl = computed(() => {
    if (!eventFilter.value) return '#';
    return `/school-admin/${school.value.id}/fest/${eventFilter.value}/food-coupons/print`;
});

function applyFilter() {
    const params = eventFilter.value ? { event_id: eventFilter.value } : {};
    router.get(`/school-admin/${school.value.id}/food-coupons`, params, { preserveState: true });
}
</script>

