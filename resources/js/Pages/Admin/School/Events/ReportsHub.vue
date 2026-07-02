<template>
    <SchoolAdminLayout title="Fest Reports" :school="school" :show-header-title="false">
        <PageHeader title="Fest Reports" description="Reports and exports by program type." />
        <div class="hub-grid">
            <HubCard
                v-for="p in programs"
                :key="p.slug"
                :href="`/school-admin/${school.id}/programs/${p.slug}/reports`"
                :label="p.label"
                icon="📊"
            />
        </div>
        <div class="mt-6">
            <h3 class="section-title mb-2">Qualifiers</h3>
            <p class="text-sm text-gray-500 mb-3">Students and teachers promoted to the next fest level.</p>
            <div class="flex flex-wrap gap-2">
                <a v-for="p in programs" :key="'q-'+p.slug"
                   :href="`/school-admin/${school.id}/programs/${p.slug}/qualifiers`"
                   class="btn-secondary text-xs">{{ p.label }} qualifiers</a>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
defineProps({ school: Object, programs: Array });
</script>
