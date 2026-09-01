<template>
    <SchoolAdminLayout title="Kalotsav" :school="school" :show-header-title="false">
        <PageHeader title="Kalotsav" eyebrow="Kalotsav" description="All Kalotsav events for your school." />

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
            <HubCard :href="`${programBase}/registration`" icon="📝" label="Register for Sahodaya" hint="Register for Kalotsav" />
            <HubCard :href="`${programBase}/results`" icon="📊" label="Results" hint="Published scores" />
            <HubCard :href="`${programBase}/qualifiers`" icon="🎯" label="Qualifiers" hint="Promoted students" />
            <HubCard :href="`${programBase}/reports`" icon="📋" label="Reports" hint="Admit cards & exports" />
            <HubCard v-if="canManageCoordinators" :href="`/school-admin/${school.id}/users?coordinators=1`" icon="👥"
                     label="Assign coordinator" hint="Give a teacher access to Kalotsav only" />
        </div>

        <section v-if="events.length" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">Kalotsav events</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Level</th>
                            <th>Dates</th>
                            <th>Entries</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ev in events" :key="ev.id">
                            <td class="font-medium">
                                {{ ev.title }}
                                <span v-if="ev.results_published" class="ml-1.5 text-[10px] font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded-full align-middle">Results published</span>
                            </td>
                            <td><span :class="statusClass(ev.status)" class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize">{{ formatLabel(ev.status) }}</span></td>
                            <td class="text-xs">{{ ev.level_label }}</td>
                            <td class="text-xs">{{ formatDateRange(ev.event_start, ev.event_end) }}</td>
                            <td>{{ ev.registrations_count }}</td>
                            <td class="text-right">
                                <Link :href="`${programBase}/events/${ev.id}/registration`" class="link-brand text-xs">Open event →</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <div v-else class="card text-sm text-slate-400">
            No Kalotsav events yet — check back once your Sahodaya publishes one.
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: Object,
    events: { type: Array, default: () => [] },
});

const { programBase } = useSchoolProgramContext(props);
const page = usePage();
const canManageCoordinators = computed(() => !page.props.isStaff && !page.props.isEventCoordinator);

function statusClass(status) {
    return {
        registration_open: 'bg-green-50 text-green-700',
        published: 'bg-amber-50 text-amber-800',
        ongoing: 'bg-blue-50 text-blue-700',
        completed: 'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function formatLabel(value) {
    if (!value) return '';
    return value.replace(/_/g, ' ');
}

function formatDateRange(start, end) {
    if (!start && !end) return '—';
    const fmt = (v) => v ? new Date(v).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
    const s = fmt(start);
    const e = fmt(end);
    if (s && e && s !== e) return `${s} – ${e}`;
    return s || e || '—';
}
</script>
