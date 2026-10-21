<template>
    <SchoolAdminLayout :title="`${event.title} — Marks`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="School events" description="Record results by competition item. Save changes before auto-ranking." />
        <p class="mb-4"><a :href="`/school-admin/${school.id}/fest-programs/${event.id}`" class="link-brand text-sm">← Back to event</a></p>
        <SchoolRoundMarks :school="school" :event="event" :registrations="registrations" :marks="marks" :grade-options="gradeOptions" :save-url="`/school-admin/${school.id}/fest-programs/${event.id}/marks`" />
    </SchoolAdminLayout>
</template>
<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SchoolRoundMarks from '@/Components/events/SchoolRoundMarks.vue';
defineProps({
    school: Object,
    event: Object,
    registrations: { type: Array, default: () => [] },
    marks: { type: Object, default: () => ({}) },
    gradeOptions: { type: Array, default: () => [] },
});
</script>
