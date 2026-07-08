<template>
    <PortalLayout
        role-label="Group Admin Portal"
        :title="`${school.name} — Fest schedule`"
        subtitle="Class-scoped view (read-only)"
        accent="violet"
        :nav-items="navItems"
    >
        <div class="card card--flush overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Student</th>
                        <th class="p-3">Event / item</th>
                        <th class="p-3">Chest</th>
                        <th class="p-3">When</th>
                        <th class="p-3">Stage</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i" class="border-t">
                        <td class="p-3">{{ row.student_name }} <span class="text-xs text-gray-400">{{ row.reg_no }}</span></td>
                        <td class="p-3">{{ row.event_title }} — {{ row.item_title }}</td>
                        <td class="p-3">{{ row.chest_no || '—' }}</td>
                        <td class="p-3">{{ row.scheduled_at ? new Date(row.scheduled_at).toLocaleString() : (row.sort_order ? `#${row.sort_order}` : '—') }}</td>
                        <td class="p-3">{{ row.stage || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="5" class="p-6 text-center text-gray-400">No scheduled fest entries.</td></tr>
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
    rows: { type: Array, default: () => [] },
});

const navItems = computed(() => groupPortalNavItems(props.school.id));
</script>
