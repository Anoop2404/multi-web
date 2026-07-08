<template>
    <PortalLayout
        role-label="House Admin Portal"
        :title="house?.name ? `${house.name} — Registrations` : 'Fest registrations'"
        :subtitle="school.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <form class="bg-white border rounded-xl p-4 flex flex-wrap gap-2 items-end mb-4" @submit.prevent="applyFilters">
            <div class="flex-1 min-w-[140px]">
                <label class="text-xs text-gray-500 block mb-1">Event</label>
                <select v-model="filterForm.event_id" class="field">
                    <option value="">All events</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                </select>
            </div>
            <div class="flex-1 min-w-[120px]">
                <label class="text-xs text-gray-500 block mb-1">Status</label>
                <select v-model="filterForm.status" class="field">
                    <option value="">All statuses</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                </select>
            </div>
            <button class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold">Filter</button>
        </form>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 text-left">
                    <tr>
                        <th class="p-3">Item</th>
                        <th class="p-3">Event</th>
                        <th class="p-3">Students</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="reg in registrations" :key="reg.id" class="border-t">
                        <td class="p-3 font-medium">{{ reg.item?.title }}</td>
                        <td class="p-3 text-gray-600">{{ reg.event?.title }}</td>
                        <td class="p-3 text-xs text-gray-600">
                            {{ (reg.participants || []).map(p => p.student?.name).filter(Boolean).join(', ') || '—' }}
                        </td>
                        <td class="p-3">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 capitalize">{{ reg.status }}</span>
                        </td>
                    </tr>
                    <tr v-if="!registrations.length">
                        <td colspan="4" class="p-8 text-center text-gray-400">No registrations for your house students.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-gray-500 text-center">
            New registrations are submitted by the school admin. Contact your school office to register house students.
        </p>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { houseAdminPortalNavItems } from '@/support/houseAdminPortalNav.js';

const props = defineProps({
    school: Object,
    house: Object,
    registrations: Array,
    events: Array,
    filters: Object,
    statusOptions: Array,
});

const filterForm = reactive({
    event_id: props.filters?.event_id ?? '',
    status: props.filters?.status ?? '',
});

const navItems = computed(() => houseAdminPortalNavItems(props.school.id));

function applyFilters() {
    const params = {};
    if (filterForm.event_id) params.event_id = filterForm.event_id;
    if (filterForm.status) params.status = filterForm.status;
    router.get(`/portal/house-admin/${props.school.id}/registrations`, params, { preserveState: true });
}
</script>

