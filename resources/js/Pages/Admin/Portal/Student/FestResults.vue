<template>
    <PortalLayout
        role-label="Student Portal"
        title="Fest results"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card mb-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="font-semibold text-sm">Fest Results</h2>
                <a :href="`/portal/student/${school.id}/sports-results`" class="text-xs text-indigo-600 font-semibold">Sports results →</a>
            </div>
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
                <li v-if="!festResults.length" class="py-4 text-center text-gray-400">
                    <p>No published fest results yet.</p>
                    <a :href="`/portal/student/${school.id}/sports-results`" class="text-xs text-indigo-600 font-semibold mt-2 inline-block">View sports results →</a>
                </li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    festResults: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
</script>
