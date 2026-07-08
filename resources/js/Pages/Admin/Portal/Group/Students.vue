<template>
    <PortalLayout
        role-label="Group Admin Portal"
        title="Students"
        accent="violet"
        :nav-items="navItems"
    >
        <input v-model="search" class="w-full border rounded-lg px-3 py-2 text-sm mb-4" placeholder="Search by name or reg no…">

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Reg No</th>
                        <th class="p-3">Class</th>
                        <th class="p-3">Gender</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in filtered" :key="s.id" class="border-t">
                        <td class="p-3 font-medium">{{ s.name }}</td>
                        <td class="p-3 font-mono text-xs">{{ s.reg_no }}</td>
                        <td class="p-3">{{ s.school_class?.name }}</td>
                        <td class="p-3 capitalize">{{ s.gender }}</td>
                    </tr>
                    <tr v-if="!filtered.length">
                        <td colspan="4" class="p-6 text-center text-gray-400">
                            <p>No students found{{ search ? ' for this search' : '' }}.</p>
                            <a :href="`/portal/group/${tenantId}`" class="text-xs text-indigo-600 font-semibold mt-2 inline-block">← Dashboard</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { ref, computed } from 'vue';
import { groupPortalNavItems } from '@/support/groupPortalNav.js';

const props = defineProps({
    tenantId: String,
    students: Array,
});

const search = ref('');
const filtered = computed(() => {
    if (! search.value.trim()) return props.students;
    const q = search.value.toLowerCase();
    return props.students.filter(s =>
        s.name?.toLowerCase().includes(q) || s.reg_no?.toLowerCase().includes(q)
    );
});

const navItems = computed(() => groupPortalNavItems(props.tenantId));
</script>
