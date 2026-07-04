<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Fest schedule"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">My schedule</h2>
            <ul v-if="festDaySlots?.length" class="text-sm divide-y">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="py-2">
                    <p class="font-medium">{{ slot.event_title }} — {{ slot.item_title }}</p>
                    <p class="text-xs text-gray-600 mt-0.5">
                        <span v-if="slot.level_reg">Reg: {{ slot.level_reg }}</span>
                        <span v-if="slot.chest_no"> · Chest #{{ slot.chest_no }}</span>
                        <span v-if="slot.stage"> · {{ slot.stage }}</span>
                    </p>
                    <p v-if="slot.scheduled_at" class="text-xs text-indigo-700 mt-0.5">
                        {{ new Date(slot.scheduled_at).toLocaleString() }}
                    </p>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400 py-2">No scheduled fest items yet</p>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    festDaySlots: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
