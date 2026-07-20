<template>
    <SchoolAdminLayout :title="`${programLabel} — Overview`" :school="school" :show-header-title="false">
        <PageHeader :title="programLabel" :eyebrow="programLabel"
                    :description="`Registration, results, reports, and fees for ${programLabel}.`">
            <template #actions>
                <Link v-if="!isTeacherFest" :href="`${programBase}/my-events`" class="btn-secondary text-sm">My school events</Link>
                <Link :href="`${programBase}/registration`" class="btn-primary text-sm">Register →</Link>
            </template>
        </PageHeader>

        <div v-if="isSports" class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 mb-6 text-sm text-emerald-950">
            <p class="font-semibold">Your sports workflow</p>
            <p class="mt-1 text-xs text-emerald-900/90">
                Register separately for each sport (Chess, Aquatics, …) under Register for Sahodaya.
                School day still uses My school events → submit winners.
            </p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-emerald-700">{{ stats.open_events }}</p>
                <p class="text-xs text-slate-500 mt-1">Open Sahodaya events</p>
            </div>
            <div v-if="stats.school_events != null" class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-indigo-700">{{ stats.school_events }}</p>
                <p class="text-xs text-slate-500 mt-1">My school events</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Your registrations</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-indigo-700">{{ stats.results_available }}</p>
                <p class="text-xs text-slate-500 mt-1">Results published</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-amber-600">₹{{ fmt(stats.fees_due) }}</p>
                <p class="text-xs text-slate-500 mt-1">Fees due</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-amber-500">{{ stats.fees_awaiting }}</p>
                <p class="text-xs text-slate-500 mt-1">Awaiting approval</p>
            </div>
        </div>

        <div v-if="isSports && ageGroups" class="notice-banner notice-banner--info mb-6 text-sm">
            Age groups registered:
            <span v-if="!registeredAgeGroups?.length" class="text-slate-500"> none yet</span>
            <span v-for="g in registeredAgeGroups" :key="g" class="font-semibold ml-1">{{ ageGroups[g] ?? g }}</span>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-8">
            <HubCard :href="`${programBase}/registration`" icon="📝" label="Register for Sahodaya" hint="Per sport: Chess, Aquatics, …" />
            <HubCard v-if="!isTeacherFest" :href="`${programBase}/my-events`" icon="🏫" label="My school events" hint="Run your own sports day" />
            <HubCard v-if="isSports" :href="`${programBase}/submit-winners`" icon="🏅" label="Submit winners" hint="Promote to Sahodaya meet" />
            <HubCard :href="`${programBase}/results`" icon="📊" label="Results" hint="Published scores" />
            <HubCard :href="`${programBase}/qualifiers`" icon="🎯" label="Qualifiers" hint="Promoted students" />
            <HubCard :href="`${programBase}/reports`" icon="📋" label="Reports" hint="Admit cards & exports" />
            <HubCard v-if="canManageCoordinators" :href="`/school-admin/${school.id}/users?coordinators=1`" icon="👥"
                     label="Assign coordinator" :hint="`Give a teacher access to ${programLabel} only`" />
        </div>

        <section v-if="schoolEvents?.length" class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">My school events</h3>
            </div>
            <table class="data-table">
                <thead><tr><th>Event</th><th>Status</th><th>Items</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="ev in schoolEvents" :key="ev.id">
                        <td class="font-medium">{{ ev.title }}</td>
                        <td class="text-xs capitalize">{{ ev.status }}</td>
                        <td>{{ ev.items_count }}</td>
                        <td class="text-right"><Link :href="ev.url" class="link-brand text-xs">Manage →</Link></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="events.length" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">Open Sahodaya events</h3>
            </div>
            <table class="data-table">
                <thead><tr><th>Event</th><th>Level</th><th>Entries</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="ev in events" :key="ev.id">
                        <td class="font-medium">{{ ev.title }}</td>
                        <td class="text-xs">{{ ev.level_label }}</td>
                        <td>{{ ev.registrations_count }}</td>
                        <td class="text-right">
                            <Link :href="`${programBase}/events/${ev.id}/overview`" class="link-brand text-xs">Open event →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object, program: Object, stats: Object, events: { type: Array, default: () => [] },
    schoolEvents: { type: Array, default: () => [] }, schoolClasses: { type: Array, default: () => [] },
    studentCount: { type: Number, default: 0 }, eventType: { type: String, default: '' },
    ageGroups: { type: Object, default: null }, registeredAgeGroups: { type: Array, default: () => [] },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const page = usePage();
const isSports = computed(() => props.eventType === 'sports');
const isTeacherFest = computed(() => props.eventType === 'teacher_fest');
const canManageCoordinators = computed(() => !page.props.isStaff && !page.props.isEventCoordinator);
function fmt(v) { return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }
</script>
