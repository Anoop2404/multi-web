<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya">
        <ProgramPlaceholderPanel :meta="meta" :view="view" />
    </SahodayaAdminLayout>
</template>

<script setup>
import ProgramPlaceholderPanel from '@/Components/ProgramPlaceholderPanel.vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    sahodaya: { type: Object, required: true },
    program:  { type: String, required: true },
    view:     { type: String, required: true },
    meta:     { type: Object, required: true },
});

const pageTitle = computed(() =>
    props.view === 'results' ? `${props.meta.label} Results` : `${props.meta.label} Registration`,
);
</script>
