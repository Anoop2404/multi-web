<template>
    <SchoolAdminLayout :title="`Region change requests — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Region change requests`" :eyebrow="programLabel"
                    :description="`Manage your school's region for each regional phase of ${event.title}.`">
            <template #actions>
                <Link :href="`${programBase}/registration?event=${event.id}`" class="btn-secondary text-sm">← Registration</Link>
            </template>
        </PageHeader>

        <div v-if="phases.length === 0" class="card text-sm text-slate-400 max-w-2xl mb-6">
            This event has no regional phases.
        </div>

        <div v-else class="space-y-4 mb-8 max-w-2xl">
            <div v-for="phase in phases" :key="phase.id" class="card space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">{{ phase.name }}</h3>
                    <span v-if="phase.current_region_name" class="text-xs font-medium text-slate-500">
                        Current: <strong class="text-slate-700">{{ phase.current_region_name }}</strong>
                        <span v-if="phase.locked" class="ml-1 text-amber-500" title="Locked">🔒</span>
                    </span>
                </div>

                <p v-if="!phase.current_region_id" class="text-xs text-slate-400">
                    No region selected yet for this phase — select one from the event's registration page first.
                </p>

                <form v-else-if="!phase.locked" class="flex flex-wrap items-center gap-2" @submit.prevent="submitDirectChange(phase)">
                    <SearchableSelect v-model="directForms[phase.id].region_id" class="min-w-[14rem]"
                                      :options="regionOptions(phase, phase.current_region_id)"
                                      :all-option="false" placeholder="Choose new region…" :required="true" />
                    <button type="submit" class="btn-primary text-xs" :disabled="directForms[phase.id].processing">Change region</button>
                </form>

                <form v-else class="space-y-2" @submit.prevent="submitRequest(phase)">
                    <p class="text-xs text-slate-500">This region is locked. Submit a request for Sahodaya admin review.</p>
                    <SearchableSelect v-model="requestForms[phase.id].requested_region_id" class="min-w-[14rem]"
                                      :options="regionOptions(phase, phase.current_region_id)"
                                      :all-option="false" placeholder="Requested region…" :required="true" />
                    <textarea v-model="requestForms[phase.id].reason" class="field text-sm" rows="2" placeholder="Reason for the change" required></textarea>
                    <p v-if="requestForms[phase.id].errors.reason" class="text-xs text-red-600">{{ requestForms[phase.id].errors.reason }}</p>
                    <button type="submit" class="btn-primary text-xs" :disabled="requestForms[phase.id].processing">Submit request</button>
                </form>
            </div>
        </div>

        <div class="card overflow-hidden p-0 max-w-3xl">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Phase</th>
                        <th>Current &rarr; Requested</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests" :key="r.id">
                        <td>{{ r.phase?.name }}</td>
                        <td class="text-sm">{{ r.current_region?.name || '—' }} &rarr; <strong>{{ r.requested_region?.name }}</strong></td>
                        <td class="text-sm max-w-xs">{{ r.reason }}</td>
                        <td>
                            <span :class="statusClass(r.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ r.status }}</span>
                            <p v-if="r.resolution_note" class="text-xs text-slate-500 mt-1 italic">Note: {{ r.resolution_note }}</p>
                        </td>
                        <td class="text-xs">{{ r.created_at ? new Date(r.created_at).toLocaleString() : '—' }}</td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td colspan="5" class="text-center text-slate-400 py-8">No region change requests yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    phases: { type: Array, default: () => [] },
    requests: { type: Array, default: () => [] },
});

const { programLabel, programBase } = useSchoolProgramContext(props);

const directForms = reactive(Object.fromEntries(
    props.phases.map((phase) => [phase.id, useForm({ phase_id: phase.id, region_id: '' })])
));
const requestForms = reactive(Object.fromEntries(
    props.phases.map((phase) => [phase.id, useForm({ phase_id: phase.id, requested_region_id: '', reason: '' })])
));

function regionOptions(phase, excludeRegionId) {
    return (phase.regions || [])
        .filter((r) => r.region_id !== excludeRegionId)
        .map((r) => ({ value: r.region_id, label: r.name }));
}

function submitDirectChange(phase) {
    directForms[phase.id].post(`${programBase.value}/events/${props.event.id}/phase-region`, { preserveScroll: true });
}

function submitRequest(phase) {
    requestForms[phase.id].post(`${programBase.value}/events/${props.event.id}/region-change-requests`, {
        preserveScroll: true,
        onSuccess: () => { requestForms[phase.id].reset('reason'); },
    });
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}
</script>
