<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Certificates"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Fest certificates</h2>
            <ul class="text-sm divide-y">
                <li v-for="(c, i) in festCerts" :key="i" class="py-2 flex justify-between gap-2">
                    <span>{{ c.event?.title ?? 'Event' }} — {{ c.item?.title ?? '' }}</span>
                    <a v-if="c.uuid" :href="`/certificates/print/${c.uuid}`" target="_blank" class="text-xs font-semibold text-indigo-600 shrink-0">Print ↗</a>
                </li>
                <li v-if="!festCerts?.length" class="text-gray-400 py-2">No certificates yet</li>
            </ul>
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
    festCerts: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
