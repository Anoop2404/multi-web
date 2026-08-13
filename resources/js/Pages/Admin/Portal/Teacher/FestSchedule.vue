<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Fest schedule"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="section-title text-base mb-3">My schedule</h2>
            <ul v-if="festDaySlots?.length" class="activity-timeline">
                <li v-for="(slot, i) in festDaySlots" :key="i" class="activity-item">
                    <span class="activity-dot" />
                    <div class="min-w-0 flex-1 pb-1">
                        <p class="font-medium text-slate-900">{{ slot.event_title }} — {{ slot.item_title }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            <span v-if="slot.level_reg">Reg: {{ slot.level_reg }}</span>
                            <span v-if="slot.chest_no"> · Chest #{{ slot.chest_no }}</span>
                            <span v-if="slot.stage"> · {{ slot.stage }}</span>
                        </p>
                        <p v-if="slot.scheduled_at" class="text-xs font-semibold text-[#0f3d7a] mt-0.5">
                            {{ new Date(slot.scheduled_at).toLocaleString() }}
                        </p>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No scheduled fest items yet" description="Your fest event schedule will appear here once it's published." icon="🎭" />
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { computed } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    festDaySlots: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
