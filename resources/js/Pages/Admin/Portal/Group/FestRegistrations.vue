<template>
    <PortalLayout
        role-label="Group Admin Portal"
        :title="`${school.name} — Fest registrations`"
        subtitle="Class-scoped view (read-only)"
        accent="violet"
        :nav-items="navItems"
    >
        <div class="card">
            <p class="text-xs text-gray-500 mb-4">Registrations for students in your assigned classes.</p>
            <ul class="divide-y text-sm">
                <li v-for="reg in registrations" :key="reg.id" class="py-3">
                    <p class="font-medium">{{ reg.event_title }} — {{ reg.item_title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 capitalize">{{ reg.status }} · {{ reg.students?.map(s => s.name).join(', ') }}</p>
                </li>
                <li v-if="!registrations.length" class="py-6 text-center text-gray-400">No fest registrations in your classes.</li>
            </ul>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { groupPortalNavItems } from '@/support/groupPortalNav.js';

const props = defineProps({
    school: Object,
    registrations: { type: Array, default: () => [] },
});

const navItems = computed(() => groupPortalNavItems(props.school.id));
</script>
