<template>
    <SchoolAdminLayout :title="`${event.title} — Fest Hub`" :school="school" :show-header-title="false">
        <PageHeader
            :title="event.title"
            eyebrow="Fest Hub"
            :description="`Active event tools & entries · ${programLabel}`"
        >
            <template #actions>
                <Link :href="registrationUrl" class="btn-primary">Open registration</Link>
            </template>
        </PageHeader>

        <!-- Active Fests & Custom Events Switcher Bar -->
        <div v-if="allEvents && allEvents.length > 1" class="mb-6 card bg-slate-50/80 border-slate-200 p-4">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                    <span>🏆</span> Active Fests & Custom Events ({{ allEvents.length }})
                </h4>
                <span class="text-xs text-slate-400 font-medium">Click an event to manage tools</span>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                <Link
                    v-for="ev in allEvents"
                    :key="ev.id"
                    :href="`/school-admin/${school.id}/fest/hub?event_id=${ev.id}`"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-2 border"
                    :class="ev.id === event.id ? 'bg-[#0f3d7a] text-white border-[#0f3d7a] shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-100'"
                >
                    <span>{{ eventIcon(ev.event_type) }}</span>
                    <span>{{ ev.title }}</span>
                </Link>
            </div>
        </div>

        <SchoolEventWorkflowStepper
            :school-id="school.id"
            :program-prefix="programPrefix"
            :event-id="event.id"
            :is-sports="false"
            current-step="overview" />

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
import SchoolEventWorkflowStepper from '@/Components/school/SchoolEventWorkflowStepper.vue';
import { SLUG_TO_PREFIX } from '@/support/schoolProgramNav.js';
import { schoolProgramHref } from '@/support/schoolProgramNav.js';
import { studentDisplayName } from '@/support/studentDisplay.js';

const props = defineProps({
    school: Object,
    event: Object,
    allEvents: { type: Array, default: () => [] },
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
const programPrefix = computed(() => SLUG_TO_PREFIX[props.programSlug] ?? props.programSlug);
const resultsUrl = computed(() => schoolProgramHref(props.school.id, props.programSlug, 'results'));

function eventIcon(type) {
    return {
        kalolsavam: '🏆',
        sports: '🏅',
        kids_fest: '🎈',
        teacher_fest: '👩‍🏫',
        english_fest: '📖',
        science_fest: '🔬',
    }[type] ?? '📅';
}

function participantName(appeal) {
    const p = appeal.participant;
    return p?.student ? studentDisplayName(p.student) : (p?.teacher?.name ?? '—');
}

function statusClass(status) {
    const map = {
        pending:  'bg-amber-100 text-amber-700',
        approved: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-red-100 text-red-700',
    };
    return map[status] ?? 'bg-slate-100 text-slate-600';
}
</script>
