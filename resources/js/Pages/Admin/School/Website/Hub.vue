<template>
    <SchoolAdminLayout title="Website" :school="school" :show-header-title="false">
        <PageHeader title="Website" eyebrow="Public website"
                    description="Manage your school's public website — sections, content, pages, and design.">
        </PageHeader>

        <div class="hub-grid">
            <HubCard :href="links.site_builder" icon="🧩" label="Website Content Management" hint="Edit website sections, page text, navigation, and footer" />
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import HubCard from '@/Components/ui/HubCard.vue';

defineProps({
    school: Object,
    links: Object,
});
</script>
