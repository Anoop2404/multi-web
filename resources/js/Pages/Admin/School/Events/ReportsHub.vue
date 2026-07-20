<template>
    <SchoolAdminLayout title="All Fest Reports Hub" :school="school" :show-header-title="false">
        <PageHeader title="All Fest Reports Hub" eyebrow="Cross-program overview" description="Select a fest program below to view event-level reports, participant exports, and qualifiers." />
        <div class="hub-grid">
            <HubCard
                v-for="p in programs"
                :key="p.slug"
                :href="schoolProgramHref(school.id, p.slug, 'reports')"
                :label="p.label"
                icon="📊"
            />
        </div>
        <div class="mt-6">
            <h3 class="section-title mb-2">Qualifiers</h3>
            <p class="text-sm text-gray-500 mb-3">Students and teachers promoted to the next fest level.</p>
            <div class="flex flex-wrap gap-2">
                <Link v-for="p in programs" :key="'q-'+p.slug"
                      :href="schoolProgramHref(school.id, p.slug, 'qualifiers')"
                      class="btn-secondary text-xs">{{ p.label }} qualifiers</Link>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { schoolProgramHref } from '@/support/schoolProgramNav.js';

defineProps({ school: Object, programs: Array });
</script>
