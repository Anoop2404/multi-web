<template>
    <SchoolAdminLayout :title="pageTitle" :school="school">
        <ProgramPlaceholderPanel :meta="meta" :view="view" school-portal />
    </SchoolAdminLayout>
</template>

<script setup>
import ProgramPlaceholderPanel from '@/Components/ProgramPlaceholderPanel.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    school:  { type: Object, required: true },
    program: { type: String, required: true },
    view:    { type: String, required: true },
    meta:    { type: Object, required: true },
});

const pageTitle = computed(() =>
    props.view === 'results' ? `${props.meta.label} Results` : `${props.meta.label} Registration`,
);
</script>
