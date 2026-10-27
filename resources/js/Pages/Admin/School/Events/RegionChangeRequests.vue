<template>
    <SchoolAdminLayout :title="`Region change requests — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Region change requests`" :eyebrow="programLabel"
                    :description="eventScoped ? `Request a change to your school’s region for ${event.title}.` : `Manage your school’s region for each regional phase of ${event.title}.`">
            <template #actions>
                <Link :href="`${programBase}/registration?event=${event.id}`" class="btn-secondary text-sm min-h-11 w-full sm:w-auto justify-center">← Registration</Link>
            </template>
        </PageHeader>

        <div v-if="phases.length === 0" class="card text-sm text-slate-400 max-w-2xl mb-6">
            This fest has no regions available for change requests.
        </div>

        <div v-else class="space-y-4 mb-8 max-w-2xl">
            <div v-for="phase in phases" :key="phase.id" class="card min-w-0 space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <h3 class="font-semibold text-slate-900">{{ phase.name }}</h3>
                    <span v-if="phase.current_region_name" class="text-sm font-medium text-slate-600 break-words">
                        Current: <strong class="text-slate-700">{{ phase.current_region_name }}</strong>
                        <span v-if="phase.locked" class="ml-1 text-amber-500" title="Locked">🔒</span>
                    </span>
                </div>

                <p v-if="!phase.current_region_id" class="text-xs text-slate-400">
                    No region selected yet — select one from this fest’s registration page first.
                </p>

                <p v-else-if="pendingRequest(phase)" role="status" class="text-sm text-amber-800">
                    Your region change request is awaiting Sahodaya approval. See its details below.
                </p>
                <p v-else-if="!regionOptions(phase, phase.current_region_id).length" class="text-sm text-slate-500">
                    No other regions are configured for this fest{{ eventScoped ? '' : ' phase' }}. Contact your Sahodaya.
                </p>

                <form v-else-if="!phase.locked" class="flex flex-wrap items-center gap-2" @submit.prevent="submitDirectChange(phase)">
                    <label :for="`direct-region-${phase.id}`" class="block w-full text-sm font-semibold">New region</label>
                    <select :id="`direct-region-${phase.id}`" v-model="directForms[phase.id].region_id" class="field w-full min-w-0 min-h-11 !text-base" required>
                        <option disabled value="">Choose a region…</option>
                        <option v-for="region in regionOptions(phase, phase.current_region_id)" :key="region.value" :value="region.value">{{ region.label }}</option>
                    </select>
                    <p v-for="(error, key) in directForms[phase.id].errors" :key="key" class="text-xs text-red-600">{{ error }}</p>
                    <button type="submit" class="btn-primary min-h-11 w-full sm:w-auto justify-center text-sm" :disabled="directForms[phase.id].processing || !directForms[phase.id].region_id">Change region</button>
                </form>

                <form v-else class="space-y-2" @submit.prevent="submitRequest(phase)">
                    <p class="text-xs text-slate-500">This region is locked. Submit a request for Sahodaya admin review.</p>
                    <label :for="`requested-region-${phase.id}`" class="block text-sm font-semibold">Requested region</label>
                    <select :id="`requested-region-${phase.id}`" v-model="requestForms[phase.id].requested_region_id" class="field w-full min-w-0 min-h-11 !text-base" required :aria-describedby="`region-help-${phase.id}`">
                        <option disabled value="">Choose a region…</option>
                        <option v-for="region in regionOptions(phase, phase.current_region_id)" :key="region.value" :value="region.value">{{ region.label }}</option>
                    </select>
                    <p :id="`region-help-${phase.id}`" class="text-xs text-slate-500">{{ regionOptions(phase, phase.current_region_id).length }} alternative region{{ regionOptions(phase, phase.current_region_id).length === 1 ? '' : 's' }} available for this fest{{ eventScoped ? '' : ' phase' }}. Your current region is excluded.</p>
                    <label :for="`region-reason-${phase.id}`" class="block text-sm font-semibold">Reason for the change</label>
                    <textarea :id="`region-reason-${phase.id}`" v-model="requestForms[phase.id].reason" class="field w-full !text-base" rows="3" maxlength="2000" placeholder="Tell your Sahodaya why you need to move" required></textarea>
                    <p v-for="(error, key) in requestForms[phase.id].errors" :key="key" class="text-xs text-red-600">{{ error }}</p>
                    <button type="submit" class="btn-primary min-h-11 w-full sm:w-auto justify-center text-sm" :disabled="requestForms[phase.id].processing || !requestForms[phase.id].requested_region_id || !requestForms[phase.id].reason.trim()">{{ requestForms[phase.id].processing ? 'Submitting…' : 'Submit change request' }}</button>
                </form>
            </div>
        </div>

        <section class="max-w-3xl mb-4 sm:hidden" aria-label="Region change history">
            <h3 class="text-base font-semibold mb-3">Request history</h3>
            <p v-if="!requests.length" class="card text-sm text-slate-500">No region change requests yet.</p>
            <article v-for="request in requests" :key="request.id" class="card mb-3 min-w-0 space-y-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <strong class="break-words">{{ request.phase?.name || event.title }}</strong>
                    <span :class="statusClass(request.status)" class="text-xs font-semibold px-2 py-1 rounded capitalize">{{ request.status }}</span>
                </div>
                <dl class="space-y-2 break-words">
                    <div><dt class="text-xs text-slate-500">From</dt><dd>{{ request.current_region?.name || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Requested region</dt><dd class="font-semibold">{{ request.requested_region?.name || '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Reason</dt><dd class="whitespace-pre-wrap">{{ request.reason }}</dd></div>
                    <div v-if="request.resolution_note"><dt class="text-xs text-slate-500">Review note</dt><dd>{{ request.resolution_note }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Submitted</dt><dd>{{ request.created_at ? new Date(request.created_at).toLocaleString() : '—' }}</dd></div>
                </dl>
            </article>
        </section>
        <div class="hidden sm:block card overflow-x-auto p-0 max-w-3xl">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ eventScoped ? 'Fest' : 'Phase' }}</th>
                        <th>Current &rarr; Requested</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests" :key="r.id">
                        <td>{{ r.phase?.name || event.title }}</td>
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
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    eventScoped: { type: Boolean, default: false },
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
        .filter((r) => Number(r.region_id) !== Number(excludeRegionId))
        .map((r) => ({ value: r.region_id, label: r.name }));
}

function pendingRequest(phase) {
    return props.requests.find(request => request.status === 'pending'
        && (props.eventScoped ? request.phase_id == null : Number(request.phase_id) === Number(phase.id)));
}

function submitDirectChange(phase) {
    directForms[phase.id].post(`${programBase.value}/events/${props.event.id}/phase-region`, { preserveScroll: true });
}

function submitRequest(phase) {
    requestForms[phase.id].post(`${programBase.value}/events/${props.event.id}/region-change-requests`, {
        preserveScroll: true,
        onSuccess: () => { requestForms[phase.id].reset(); },
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
