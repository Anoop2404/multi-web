<template>
    <PortalLayout role-label="Group Admin Portal" title="Fest Results" accent="indigo" :nav-items="navItems">
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Event</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Students</th>
                        <th class="p-3">Place</th>
                        <th class="p-3">Grade</th>
                        <th class="p-3">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in results" :key="row.id" class="border-t">
                        <td class="p-3 font-medium">{{ row.event_title }}</td>
                        <td class="p-3 text-gray-600">{{ row.item_name }}<span v-if="row.item_code" class="text-gray-400"> ({{ row.item_code }})</span></td>
                        <td class="p-3 text-gray-600">{{ row.students }}</td>
                        <td class="p-3 font-bold">{{ row.place }}</td>
                        <td class="p-3">{{ row.grade }}</td>
                        <td class="p-3 font-mono font-semibold">{{ row.points }}</td>
                    </tr>
                    <tr v-if="!results.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No published results yet for your group's students.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { groupPortalNavItems } from '@/support/groupPortalNav.js';

const props = defineProps({
    school: Object,
    results: { type: Array, default: () => [] },
});

const navItems = computed(() => groupPortalNavItems(props.school.id));
</script>
