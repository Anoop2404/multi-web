<template>
    <SchoolAdminLayout title="Website" :school="school" :show-header-title="false">
        <PageHeader title="Website" eyebrow="Public website"
                    description="Manage your school's public website — content, design, and modules.">
        </PageHeader>

        <!-- Enable/disable banner -->
        <div class="mb-6 rounded-2xl border shadow-sm p-5 flex flex-wrap items-center justify-between gap-4"
             :class="publicWebsiteEnabled ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
            <div>
                <h3 class="font-bold text-gray-900">Public Website</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ publicWebsiteEnabled ? 'Your website is visible to the public.' : 'Your website is currently offline. Enable it below to publish.' }}
                </p>
            </div>
            <button type="button"
                    @click="togglePublicWebsite"
                    :disabled="toggleSaving"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold transition shadow-sm disabled:opacity-50"
                    :class="publicWebsiteEnabled
                        ? 'bg-amber-600 hover:bg-amber-700 text-white'
                        : 'bg-emerald-600 hover:bg-emerald-700 text-white'">
                {{ toggleSaving ? 'Saving…' : (publicWebsiteEnabled ? 'Take Offline' : 'Go Live') }}
            </button>
        </div>

        <!-- Readiness score -->
        <div v-if="readiness" class="mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-gray-900">Website readiness</h3>
                <span class="text-2xl font-bold" :class="readiness.percentage === 100 ? 'text-emerald-600' : readiness.percentage >= 60 ? 'text-amber-600' : 'text-red-600'">
                    {{ readiness.percentage }}%
                </span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-3 mb-4">
                <div class="h-3 rounded-full transition-all duration-500"
                     :class="readiness.percentage === 100 ? 'bg-emerald-500' : readiness.percentage >= 60 ? 'bg-amber-500' : 'bg-red-500'"
                     :style="{ width: readiness.percentage + '%' }"></div>
            </div>
            <div v-if="readiness.missing?.length" class="space-y-1.5">
                <p class="text-xs font-bold text-gray-600 uppercase tracking-wider">To complete setup</p>
                <div v-for="item in readiness.missing" :key="item" class="flex items-center gap-2 text-sm text-gray-600">
                    <span class="text-amber-500">○</span>
                    <span>{{ item }}</span>
                </div>
            </div>
            <p v-else class="text-sm text-emerald-700 font-medium">All setup steps complete!</p>
        </div>

        <!-- Module cards -->
        <div class="hub-grid">
            <HubCard :href="links.site_builder" icon="🧩" label="Site Builder"
                     hint="Sections, pages, navigation, design and footer" />

            <div v-if="stats" class="hub-card hub-card--static cursor-pointer"
                 @click="$router.visit(links.news)">
                <span class="hub-card-icon" aria-hidden="true">📰</span>
                <span class="hub-card-label">News</span>
                <span class="hub-card-hint">{{ stats.news_count }} published</span>
            </div>

            <div v-if="stats" class="hub-card hub-card--static cursor-pointer"
                 @click="$router.visit(links.events)">
                <span class="hub-card-icon" aria-hidden="true">📅</span>
                <span class="hub-card-label">Events</span>
                <span class="hub-card-hint">{{ stats.upcoming_events_count }} upcoming</span>
            </div>

            <HubCard :href="links.gallery" icon="🖼️" label="Gallery" hint="Photo albums and media" />

            <HubCard :href="links.staff" icon="👥" label="Staff Directory" hint="Teachers and administration" />

            <HubCard :href="links.enquiries" icon="📝" label="Admission Enquiries"
                     hint="Form submissions and leads" />

            <HubCard :href="links.forms" icon="📋" label="Form Submissions" hint="Website form responses" />

            <HubCard :href="links.job_vacancies" icon="💼" label="Job Vacancies" hint="Open positions" />

            <HubCard :href="links.alumni" icon="🎓" label="Alumni" hint="Registrations and approvals" />

            <HubCard :href="links.downloads" icon="📥" label="Downloads" hint="Documents and resources" />

            <HubCard :href="links.testimonials" icon="💬" label="Testimonials" hint="Reviews and feedback" />

            <div v-if="stats" class="hub-card hub-card--static">
                <span class="hub-card-icon" aria-hidden="true">🔗</span>
                <span class="hub-card-label">Domain &amp; SSL</span>
                <span class="hub-card-hint" :class="stats.domain_verified ? 'text-emerald-600' : 'text-amber-600'">
                    {{ stats.domain_verified ? 'Domain verified' : 'Domain not configured' }}
                </span>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import HubCard from '@/Components/ui/HubCard.vue';

const props = defineProps({
    school: Object,
    links: Object,
    publicWebsiteEnabled: Boolean,
    readiness: Object,
    stats: Object,
});

const toggleSaving = ref(false);

async function togglePublicWebsite() {
    if (!confirm(props.publicWebsiteEnabled ? 'Take the website offline? It will no longer be visible to the public.' : 'Publish the website? It will be visible to the public.')) return;
    toggleSaving.value = true;
    try {
        await router.post('/school-admin/' + props.school.id + '/site-builder/api/public-website', {
            enabled: !props.publicWebsiteEnabled,
        }, {
            onSuccess: () => {},
            onError: () => {},
        });
    } finally {
        toggleSaving.value = false;
    }
}
</script>
