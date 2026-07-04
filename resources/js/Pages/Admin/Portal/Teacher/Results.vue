<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Fest results"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Published results</h2>
            <ul class="text-sm divide-y">
                <li v-for="(r, i) in festResults" :key="i" class="py-2">
                    <p class="font-medium">{{ r.event_title }} — {{ r.item_title }}</p>
                    <p v-if="r.grade || r.position || r.score" class="text-xs text-indigo-700 mt-0.5">
                        <span v-if="r.grade">Grade: {{ r.grade }}</span>
                        <span v-if="r.position"> · Position: {{ r.position }}</span>
                        <span v-if="r.score"> · Score: {{ r.score }}</span>
                        <span v-if="r.chest_no"> · Chest #{{ r.chest_no }}</span>
                    </p>
                    <p v-else class="text-xs text-gray-400 mt-0.5">Results not yet recorded</p>
                </li>
                <li v-if="!festResults?.length" class="text-gray-400 py-2">No published fest results yet</li>
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
    festResults: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
