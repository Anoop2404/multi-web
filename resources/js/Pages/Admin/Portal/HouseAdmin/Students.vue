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
                        <td colspan="4" class="p-8 text-center text-gray-400">
                            <p>No students in this house.</p>
                            <a :href="`/portal/house-admin/${tenantId}`" class="text-xs text-emerald-700 font-semibold mt-2 inline-block">← Dashboard</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { houseAdminPortalNavItems } from '@/support/houseAdminPortalNav.js';

const props = defineProps({
    tenantId: String,
    house: Object,
    students: Array,
});

const navItems = computed(() => houseAdminPortalNavItems(props.tenantId));
</script>
