<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Training"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Training programs</h2>
            <div v-for="t in training" :key="t.id" class="border-t first:border-0 pt-3 first:pt-0 mb-3">
                <p class="font-medium text-sm">{{ t.program?.title }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ t.status }}</p>
                <ul v-if="t.sessions?.length" class="mt-2 text-xs space-y-1">
                    <li v-for="s in t.sessions" :key="s.id" class="text-gray-600">
                        {{ s.title }} · {{ s.scheduled_at ? new Date(s.scheduled_at).toLocaleString() : 'TBA' }}
                        <span v-if="s.venue"> · {{ s.venue }}</span>
                        <span v-if="s.attendance" class="ml-1 capitalize">({{ s.attendance }})</span>
                    </li>
                </ul>
                <a v-if="t.certificate_uuid" :href="`/portal/teacher/${school.id}/training/${t.id}/certificate`" target="_blank"
                   class="text-xs font-semibold text-indigo-600 mt-1 inline-block">Download certificate ↗</a>
            </div>
            <p v-if="!training?.length" class="text-sm text-gray-400 py-4">No training registrations yet.</p>
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
    training: { type: Array, default: () => [] },
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
</script>
