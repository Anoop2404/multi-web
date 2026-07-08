<template>
    <SchoolAdminLayout :title="`${event.title} — Fest Hub`" :school="school" :show-header-title="false">
        <PageHeader
            :title="event.title"
            eyebrow="Fest Hub"
            :description="`Registration status: ${registrations.length} entries · ${programLabel}`"
        >
            <template #actions>
                <Link :href="registrationUrl" class="btn-primary">Open registration</Link>
            </template>
        </PageHeader>

        <div class="hub-grid mb-6">
            <HubCard
                :href="`/school-admin/${school.id}/fest/${event.id}/house`"
                icon="🏠"
                label="House standings"
                hint="School house points for this fest"
            />
            <HubCard
                :href="`/school-admin/${school.id}/fest/${event.id}/catering`"
                icon="🍽"
                label="Meal requests"
                hint="Catering and food service"
            />
            <HubCard
                :href="registrationUrl"
                icon="📝"
                label="Registrations"
                :hint="`Manage ${programLabel} entries`"
            />
            <HubCard
                :href="`/school-admin/${school.id}/fest/${event.id}/appeals`"
                icon="⚖️"
                label="Appeals"
                :hint="`${appeals.length ? appeals.length + ' appeal(s)' : 'Submit & track appeals'}`"
            />
            <HubCard
                :href="resultsUrl"
                icon="📊"
                label="Results"
                hint="Published scores and ranks"
            />
        </div>

        <div v-if="appeals.length" class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="section-title">Recent appeals</h3>
                <Link :href="`/school-admin/${school.id}/fest/${event.id}/appeals`" class="text-xs font-semibold text-[#0f3d7a]">
                    View all →
                </Link>
            </div>
            <ul class="divide-y text-sm">
                <li v-for="a in appeals" :key="a.id" class="py-2 flex flex-wrap justify-between gap-2">
                    <span class="min-w-0">
                        <span class="font-medium">{{ participantName(a) }}</span>
                        <span class="text-slate-500 text-xs block truncate">{{ a.participant?.registration?.item?.title }}</span>
                    </span>
                    <span :class="statusClass(a.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize shrink-0">{{ a.status }}</span>
                </li>
            </ul>
        </div>

        <div class="card">
            <h3 class="section-title mb-1">Quick appeal</h3>
            <p class="section-desc mb-4">Submit a new appeal or open the appeals page for full history.</p>
            <Link :href="`/school-admin/${school.id}/fest/${event.id}/appeals`" class="btn-secondary text-sm">Open appeals →</Link>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { schoolProgramHref } from '@/support/schoolProgramNav.js';

const props = defineProps({
    school: Object,
    event: Object,
    registrations: Array,
    appeals: { type: Array, default: () => [] },
    programSlug: { type: String, default: 'kalotsav' },
    registrationUrl: String,
});

const programLabels = {
    kalotsav: 'Kalotsav',
    'sports-meet': 'Sports Meet',
    'kids-fest': 'Kids Fest',
    'teacher-fest': 'Teacher Fest',
    'english-fest': 'English Fest',
    'science-fest': 'Science Fest',
    custom: 'Custom event',
};

const programLabel = computed(() => programLabels[props.programSlug] ?? 'Fest');
const resultsUrl = computed(() => schoolProgramHref(props.school.id, props.programSlug, 'results'));
</script>
