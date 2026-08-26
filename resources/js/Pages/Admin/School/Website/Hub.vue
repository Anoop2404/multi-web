<template>
    <SchoolAdminLayout title="Website hub" :school="school" :show-header-title="false">
        <PageHeader title="Website hub" eyebrow="Public website"
                    description="Manage your school's public website — content, media, staff, and admissions pages.">
        </PageHeader>

        <div class="hub-grid">
            <HubCard :href="links.site_builder" icon="🧩" label="Site builder" hint="Sections, theme, navigation & footer" />
            <HubCard :href="links.news" icon="📰" label="News" hint="Announcements and articles" />
            <HubCard :href="links.events" icon="📅" label="Events" hint="Public events calendar" />
            <HubCard :href="links.gallery" icon="🖼️" label="Gallery" hint="Photos and albums" />
            <HubCard :href="links.staff" icon="🧑‍🏫" label="Staff" hint="Staff directory listing" />
            <HubCard :href="links.achievements" icon="🏆" label="Achievements" hint="School & student achievements" />
            <HubCard :href="links.downloads" icon="📁" label="Downloads" hint="Circulars, forms, and documents" />
            <HubCard :href="links.job_vacancies" icon="💼" label="Job vacancies" hint="Staff recruitment listings" />
            <HubCard :href="links.alumni" icon="🎓" label="Alumni" hint="Alumni directory" />
            <HubCard :href="links.testimonials" icon="⭐" label="Testimonials" hint="Parent & student testimonials" />
            <HubCard :href="links.contact" icon="✉️" label="Contact page" hint="Contact details shown publicly" />
            <HubCard :href="links.enquiries" icon="📥" label="Enquiries" hint="Admission enquiries received" />
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
