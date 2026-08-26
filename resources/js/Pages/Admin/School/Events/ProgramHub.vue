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
            <HubCard :href="`${programBase}/registration`" icon="📝" label="Register for Sahodaya" :hint="isSports ? 'Per sport: Chess, Aquatics, …' : `Register for ${programLabel}`" />
            <HubCard v-if="isSports || (schoolEvents && schoolEvents.length > 0)" :href="`${programBase}/my-events`" icon="🏫" label="My school events" :hint="isSports ? 'Run your own sports day' : 'Internal school events'" />
            <HubCard v-if="isSports" :href="`${programBase}/submit-winners`" icon="🏅" label="Submit winners" hint="Promote to Sahodaya meet" />
            <HubCard :href="`${programBase}/results`" icon="📊" label="Results" hint="Published scores" />
            <HubCard :href="`${programBase}/qualifiers`" icon="🎯" label="Qualifiers" hint="Promoted students" />
            <HubCard :href="`${programBase}/reports`" icon="📋" label="Reports" hint="Admit cards & exports" />
            <HubCard v-if="canManageCoordinators" :href="`/school-admin/${school.id}/users?coordinators=1`" icon="👥"
                     label="Assign coordinator" :hint="`Give a teacher access to ${programLabel} only`" />
        </div>

        <!-- Phase-wise Region & Venue Selection Banner -->
        <section v-if="regionOptions?.phase_region_options?.length" class="card mb-6 border-amber-300 bg-amber-50/90 shadow-sm p-4 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-amber-950 flex items-center gap-1.5">
                        <span>📍</span> Choose Regions by Competition Phase
                    </h3>
                    <p class="text-xs text-amber-900 mt-1">
                        Off Stage and Sargadhara phases operate independently. Please select your school's region based on venue location to view and register for items.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div v-for="phase in regionOptions.phase_region_options" :key="phase.phase_id"
                     class="bg-white p-4 rounded-xl border border-amber-200 shadow-xs flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="font-bold text-sm text-slate-900">{{ phase.phase_name }}</span>
                            <span v-if="phase.selection?.locked" class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">Locked</span>
                            <span v-else-if="phase.selection?.region_id" class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">✓ Assigned</span>
                            <span v-else class="text-xs font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">Pending Selection</span>
                        </div>
                        <label class="block text-xs text-slate-600 font-medium mb-1">Select Region &amp; Venue</label>
                        <SearchableSelect v-model="hubPhaseChoices[phase.phase_id]"
                                class="w-full"
                                :options="phaseRegionOptions(phase)"
                                :all-option="false"
                                :placeholder="`Select a region for ${phase.phase_name}…`"
                                :disabled="phase.selection?.locked" />

                        <!-- Host Venue & Conduct Date Details Box -->
                        <div v-if="getSelectedRegionDetails(phase)" class="mt-3 p-2.5 rounded-lg bg-amber-50/70 border border-amber-200/70 text-xs space-y-1">
                            <p class="font-semibold text-slate-900 flex items-start gap-1">
                                <span>🏢 Venue:</span> <span class="text-amber-950 font-bold">{{ getSelectedRegionDetails(phase).venue || 'Venue TBA' }}</span>
                            </p>
                            <p v-if="getSelectedRegionDetails(phase).conduct_start_at" class="text-slate-700 flex items-center gap-1">
                                <span>📅 Date:</span> <span class="font-semibold text-slate-900">{{ formatDateStr(getSelectedRegionDetails(phase).conduct_start_at) }}</span>
                            </p>
                        </div>
                    </div>
                    <button v-if="!phase.selection?.locked"
                            type="button"
                            @click="saveHubPhaseRegion(phase)"
                            :disabled="!hubPhaseChoices[phase.phase_id]"
                            class="btn-primary text-xs w-full py-1.5 font-semibold">
                        {{ phase.selection?.region_id ? '✓ Change ' + phase.phase_name + ' Region' : 'Save ' + phase.phase_name + ' Region' }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Standard Single Region Selection (Fallback) -->
        <section v-else-if="regionOptions?.has_regions" class="card space-y-4 mb-6 bg-gradient-to-r from-sky-50/80 to-indigo-50/80 border-sky-200">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                        <span>📍</span> Select School Region &amp; Venue
                    </h3>
                    <p class="text-xs text-slate-600 mt-1">
                        Select your school's region based on the venue location to view your specific regional events and items.
                    </p>
                </div>
                <span v-if="activeRegionName" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold shrink-0">
                    ✓ Assigned: {{ activeRegionName }}
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 pt-1">
                <div v-for="r in regionOptions.regions" :key="r.id"
                     class="p-3.5 rounded-xl border bg-white shadow-xs transition hover:shadow-md space-y-2 flex flex-col justify-between"
                     :class="isRegionSelected(r.id) ? 'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50/30' : 'border-slate-200 hover:border-indigo-300'">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-slate-900">{{ r.name }}</span>
                            <span v-if="isRegionSelected(r.id)" class="text-xs font-semibold text-indigo-600">Selected</span>
                        </div>
                        <p v-if="r.venue_name" class="text-xs font-medium text-slate-700 flex items-center gap-1">
                            <span>🏢</span> {{ r.venue_name }}
                        </p>
                        <p v-if="r.location" class="text-xs text-slate-500 flex items-center gap-1">
                            <span>📍</span> {{ r.location }}
                        </p>
                    </div>
                    <button type="button"
                            @click="selectRegion(r.id)"
                            class="w-full text-xs font-semibold py-1.5 px-3 rounded-lg transition"
                            :class="isRegionSelected(r.id) ? 'bg-indigo-600 text-white' : 'btn-secondary'">
                        {{ isRegionSelected(r.id) ? '✓ Assigned Region' : 'Select Region →' }}
                    </button>
                </div>
            </div>
        </section>

        <section v-if="schoolEvents?.length" class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">My school events</h3>
            </div>
            <div class="overflow-x-auto">
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
            </div>
        </section>

        <section v-if="events.length" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">Open Sahodaya events</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Event</th><th>Level</th><th>Entries</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="ev in events" :key="ev.id">
                            <td class="font-medium">{{ ev.title }}</td>
                            <td class="text-xs">{{ ev.level_label }}</td>
                            <td>{{ ev.registrations_count }}</td>
                            <td class="text-right">
                                <Link :href="`${programBase}/events/${ev.id}/registration`" class="link-brand text-xs">Open event →</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object, program: Object, stats: Object, events: { type: Array, default: () => [] },
    schoolEvents: { type: Array, default: () => [] }, schoolClasses: { type: Array, default: () => [] },
    studentCount: { type: Number, default: 0 }, eventType: { type: String, default: '' },
    ageGroups: { type: Object, default: null }, registeredAgeGroups: { type: Array, default: () => [] },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    regionOptions: { type: Object, default: () => ({ has_regions: false, regions: [], assignments: {}, phase_region_options: [] }) },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const page = usePage();
const isSports = computed(() => props.eventType === 'sports');
const isTeacherFest = computed(() => props.eventType === 'teacher_fest');
const canManageCoordinators = computed(() => !page.props.isStaff && !page.props.isEventCoordinator);
function fmt(v) { return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }

const hubPhaseChoices = reactive({});

watch(() => props.regionOptions?.phase_region_options, (options) => {
    if (options && Array.isArray(options)) {
        for (const phase of options) {
            if (phase.phase_id && hubPhaseChoices[phase.phase_id] === undefined) {
                hubPhaseChoices[phase.phase_id] = phase.selection?.region_id || '';
            }
        }
    }
}, { immediate: true, deep: true });

function saveHubPhaseRegion(phase) {
    const regionId = hubPhaseChoices[phase.phase_id];
    if (!regionId || !phase.event_id) return;
    router.post(`${programBase.value}/events/${phase.event_id}/phase-region`, {
        phase_id: phase.phase_id,
        region_id: regionId,
    }, { preserveScroll: true });
}

function phaseRegionOptions(phase) {
    return (phase.regions || []).map(r => ({
        value: r.id,
        label: `${r.name}${r.venue ? ` — ${r.venue}` : ''}`,
    }));
}

function getSelectedRegionDetails(phase) {
    const selectedId = hubPhaseChoices[phase.phase_id] || phase.selection?.region_id;
    if (!selectedId) return null;
    return phase.regions?.find(r => String(r.id) === String(selectedId)) || null;
}

function formatDateStr(val) {
    if (!val) return '';
    const d = new Date(val);
    if (isNaN(d.getTime())) return val;
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function isRegionSelected(regionId) {
    const defaultAssigned = props.regionOptions?.assignments?.default;
    return String(defaultAssigned) === String(regionId);
}

const activeRegionName = computed(() => {
    const assignedId = props.regionOptions?.assignments?.default;
    if (!assignedId) return null;
    return props.regionOptions?.regions?.find(r => String(r.id) === String(assignedId))?.name;
});

function selectRegion(regionId) {
    router.post(`${programBase.value}/select-region`, { region_id: regionId }, { preserveScroll: true });
}
</script>
