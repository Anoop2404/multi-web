<template>
    <PortalLayout role-label="House Admin Portal" :title="`${house?.name || 'House'} Ranking`" accent="emerald" :nav-items="navItems">
        <form class="mb-4 flex gap-2 items-center">
            <label class="text-xs text-gray-500">Event filter</label>
            <select v-model="eventFilter" @change="applyFilter" class="field text-sm max-w-xs">
                <option value="">All events (cumulative)</option>
                <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
            </select>
        </form>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Rank</th>
                        <th class="p-3">House</th>
                        <th class="p-3">Points</th>
                        <th class="p-3">Participants</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in ranking" :key="row.house_id" class="border-t"
                        :class="house?.id === row.house_id ? 'bg-emerald-50' : ''">
                        <td class="p-3 font-bold">#{{ row.rank }}</td>
                        <td class="p-3 font-medium flex items-center gap-2">
                            <span v-if="row.color" class="w-3 h-3 rounded-full" :style="{ background: row.color }"></span>
                            {{ row.house_name }}
                            <span v-if="house?.id === row.house_id" class="text-xs text-emerald-700">(my house)</span>
                        </td>
                        <td class="p-3 font-mono font-semibold">{{ row.total_points }}</td>
                        <td class="p-3 text-gray-500">{{ row.participants }}</td>
                    </tr>
                    <tr v-if="!ranking.length">
                        <td colspan="4" class="p-8 text-center text-gray-400">No house points yet — assign students to houses and enter fest marks.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    house: Object,
    ranking: Array,
    events: Array,
    selectedEvent: Number,
});

const eventFilter = ref(props.selectedEvent ?? '');
const navItems = computed(() => [
    { href: `/portal/house-admin/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/house-admin/${props.school.id}/students`, label: 'Students' },
    { href: `/portal/house-admin/${props.school.id}/registrations`, label: 'Registrations' },
    { href: `/portal/house-admin/${props.school.id}/ranking`, label: 'House ranking' },
]);

function applyFilter() {
    const q = eventFilter.value ? `?event_id=${eventFilter.value}` : '';
    router.get(`/portal/house-admin/${props.school.id}/ranking${q}`, {}, { preserveState: true });
}
</script>

