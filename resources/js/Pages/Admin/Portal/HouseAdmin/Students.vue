<template>
    <PortalLayout
        role-label="House Admin Portal"
        :title="house?.name ? `${house.name} — Students` : 'Students'"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Class</th>
                        <th class="p-3">Reg no</th>
                        <th class="p-3">Fest entries</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in students" :key="s.id" class="border-t">
                        <td class="p-3 font-medium">{{ s.name }}</td>
                        <td class="p-3 text-gray-600">{{ s.school_class?.name }}</td>
                        <td class="p-3 text-gray-500">{{ s.reg_no }}</td>
                        <td class="p-3">{{ s.fest_entries }}</td>
                    </tr>
                    <tr v-if="!students.length">
                        <td colspan="4" class="p-8 text-center text-gray-400">No students in this house.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    tenantId: String,
    house: Object,
    students: Array,
});

const navItems = computed(() => [
    { href: `/portal/house-admin/${props.tenantId}`, label: 'Dashboard' },
    { href: `/portal/house-admin/${props.tenantId}/students`, label: 'Students' },
    { href: `/portal/house-admin/${props.tenantId}/registrations`, label: 'Registrations' },
    { href: `/portal/house-admin/${props.tenantId}/ranking`, label: 'House ranking' },
]);
</script>
