<template>
    <SchoolAdminLayout title="Fest Hub — Active Events Directory" :school="school" :show-header-title="false">
        <PageHeader
            title="Fest Hub"
            eyebrow="School Portal"
            description="Central directory of all active Sahodaya fest programs, regional partitions, and custom events for your school."
        />

        <!-- All Active Fest Events & Custom Events Directory Grid -->
        <div class="mb-8">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>🏆</span> All Active Fests & Custom Events ({{ allEvents.length || 1 }})
                </h3>
            </div>

            <div v-if="allEvents && allEvents.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="ev in allEvents"
                    :key="ev.id"
                    class="card transition-all hover:shadow-md border-2 flex flex-col justify-between"
                    :class="ev.id === event.id ? 'border-[#0f3d7a] bg-slate-50/50 ring-2 ring-[#0f3d7a]/10' : 'border-slate-200 hover:border-slate-300 bg-white'"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <span class="text-2xl">{{ eventIcon(ev.event_type) }}</span>
                            <span :class="eventBadgeClass(ev.status)" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full border shrink-0">
                                {{ ev.status_label || ev.status }}
                            </span>
                        </div>

                        <h4 class="text-sm font-bold text-slate-900 leading-snug mb-1">
                            {{ ev.title }}
                        </h4>

                        <p class="text-xs text-slate-500 capitalize mb-3">
                            {{ formatEventType(ev.event_type) }} · {{ ev.level_round || 'Sahodaya' }} level
                        </p>

                        <div class="space-y-1.5 text-xs text-slate-600 bg-slate-100/70 p-2.5 rounded-lg mb-4">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-slate-500">School Registrations:</span>
                                <span class="font-bold text-slate-900">{{ ev.registrations_count ?? 0 }} entries</span>
                            </div>
                            <div v-if="ev.registration_close" class="flex justify-between items-center">
                                <span class="font-medium text-slate-500">Deadline:</span>
                                <span class="font-semibold text-amber-700 font-mono">{{ ev.registration_close }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                        <Link :href="ev.registration_url" class="btn-primary text-xs flex-1 text-center justify-center font-bold py-2">
                            Open Registration →
                        </Link>
                        <Link :href="`/school-admin/${school.id}/fest/hub?event_id=${ev.id}`" class="btn-secondary text-xs px-3 py-2 shrink-0 font-semibold">
                            Tools
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selected Event Tools Panel -->
        <div v-if="event" class="card border-2 border-indigo-100 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                <div>
                    <div class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider mb-0.5">
                        Selected Event Tools
                    </div>
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span>{{ eventIcon(event.event_type) }}</span>
                        <span>{{ event.title }}</span>
                    </h3>
                </div>
                <Link :href="registrationUrl" class="btn-primary text-xs font-bold">
                    Open Registration →
                </Link>
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

            <div v-if="appeals.length" class="card mb-6 bg-slate-50/50">
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

            <div class="card bg-slate-50/50">
                <h3 class="section-title mb-1">Quick appeal</h3>
                <p class="section-desc mb-4">Submit a new appeal or open the appeals page for full history.</p>
                <Link :href="`/school-admin/${school.id}/fest/${event.id}/appeals`" class="btn-secondary text-sm">Open appeals →</Link>
            </div>
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

function formatEventType(type) {
    if (!type) return 'Fest';
    return type.replace(/_/g, ' ');
}

function eventBadgeClass(status) {
    const map = {
        published: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        registration_open: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        ongoing: 'bg-amber-50 text-amber-700 border-amber-200',
        completed: 'bg-slate-100 text-slate-700 border-slate-200',
    };
    return map[status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
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
