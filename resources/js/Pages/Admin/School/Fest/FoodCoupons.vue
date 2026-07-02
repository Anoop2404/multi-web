<template>
    <SchoolAdminLayout title="Food coupons" :school="school" :show-header-title="false">
        <PageHeader title="Food coupons" eyebrow="Programs"
            description="Fest programs, exams, training, and Sahodaya circulars." />


        <form class="bg-white border rounded-xl p-4 flex flex-wrap gap-2 items-end mb-4" @submit.prevent="applyFilter">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500 block mb-1">Festival</label>
                <select v-model="eventFilter" class="field">
                    <option value="">All festivals</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                </select>
            </div>
            <button class="btn-primary">Filter</button>
            <a v-if="eventFilter" :href="printUrl" target="_blank"
               class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium">Print issued coupons</a>
        </form>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 text-left">
                    <tr>
                        <th class="p-3">Code</th>
                        <th class="p-3">Event</th>
                        <th class="p-3">Meal</th>
                        <th class="p-3">Valid date</th>
                        <th class="p-3">Heads</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in coupons" :key="c.id" class="border-t">
                        <td class="p-3 font-mono font-semibold">{{ c.coupon_code }}</td>
                        <td class="p-3">{{ c.event?.title }}</td>
                        <td class="p-3 capitalize">{{ c.meal_type }}</td>
                        <td class="p-3">{{ c.valid_date }}</td>
                        <td class="p-3">{{ c.head_count }}</td>
                        <td class="p-3">
                            <span class="text-xs px-2 py-0.5 rounded-full capitalize"
                                  :class="c.status === 'redeemed' ? 'bg-green-100 text-green-800' : c.status === 'void' ? 'bg-gray-100' : 'bg-amber-100 text-amber-800'">
                                {{ c.status }}
                            </span>
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
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    events: Array,
    coupons: Array,
    filters: Object,
});

const school = computed(() => usePage().props.school);
const eventFilter = ref(props.filters?.event_id ?? '');

const printUrl = computed(() => {
    if (!eventFilter.value) return '#';
    return `/school-admin/${school.value.id}/fest/${eventFilter.value}/food-coupons/print`;
});

function applyFilter() {
    const params = eventFilter.value ? { event_id: eventFilter.value } : {};
    router.get(`/school-admin/${school.value.id}/food-coupons`, params, { preserveState: true });
}
</script>

