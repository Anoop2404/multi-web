<template>
    <SchoolAdminLayout :title="`Clash reports — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Schedule clash reports`" :eyebrow="programLabel"
                    description="Report overlapping schedules for your participants.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}/schedule-clashes`" class="btn-secondary text-sm">Detected clashes</Link>
                <Link :href="`${programBase}/registration?event=${event.id}`" class="btn-secondary text-sm">← Registration</Link>
            </template>
        </PageHeader>

        <form class="card mb-6 max-w-2xl space-y-3" @submit.prevent="submit">
            <h3 class="font-semibold text-slate-900">Report a clash</h3>
            <SearchableSelect v-model="form.participant_id" :options="participantOptions" :all-option="true"
                              all-label="Select participant" :required="true" @change="onParticipantChange" />
            <SearchableSelect v-if="participantSchedules.length" v-model="form.schedule_id_a" :options="scheduleOptions"
                              :all-option="true" all-label="Schedule slot A (optional)" />
            <SearchableSelect v-if="participantSchedules.length" v-model="form.schedule_id_b" :options="scheduleOptions"
                              :all-option="true" all-label="Schedule slot B (optional)" />
            <textarea v-model="form.description" class="field text-sm" rows="3" placeholder="Describe the clash" required />
            <textarea v-model="form.requested_resolution" class="field text-sm" rows="2" placeholder="Suggested resolution (optional)" />
            <button type="submit" class="btn-primary text-sm" :disabled="form.processing">Submit report</button>
        </form>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests" :key="r.id">
                        <td>{{ r.participant?.student ? studentDisplayName(r.participant.student) : '—' }}</td>
                        <td class="text-sm">
                            <p>{{ r.description }}</p>
                            <p v-if="r.resolution_note" class="text-slate-500 mt-1 italic">Note: {{ r.resolution_note }}</p>
                        </td>
                        <td>
                            <span :class="statusClass(r.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ r.status }}</span>
                            <p v-if="r.reviewed_at" class="text-[11px] text-slate-400 mt-1">Reviewed {{ formatTime(r.reviewed_at) }}</p>
                        </td>
                        <td class="text-xs">{{ r.created_at ? new Date(r.created_at).toLocaleString() : '—' }}</td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td colspan="4" class="text-center text-slate-400 py-8">No clash reports yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { studentDisplayName } from '@/support/studentDisplay.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    requests: { type: Array, default: () => [] },
    participants: { type: Array, default: () => [] },
});

const { programLabel, programBase } = useSchoolProgramContext(props);

const form = useForm({
    participant_id: '',
    schedule_id_a: '',
    schedule_id_b: '',
    description: '',
    requested_resolution: '',
});

const participantSchedules = computed(() => {
    const p = props.participants.find((row) => String(row.id) === String(form.participant_id));
    return p?.schedules || [];
});

const participantOptions = computed(() => props.participants.map((p) => ({
    value: p.id,
    label: `${p.student ? studentDisplayName(p.student) : p.name} — ${p.item}${p.category_label ? ` (${p.category_label})` : ''}`,
})));

const scheduleOptions = computed(() => participantSchedules.value.map((s) => ({
    value: s.id,
    label: `${s.item_title}${s.category_label ? ` (${s.category_label})` : ''} · ${formatTime(s.scheduled_at)}`,
})));

function onParticipantChange() {
    form.schedule_id_a = '';
    form.schedule_id_b = '';
}

function formatTime(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function submit() {
    form.post(`${programBase}/events/${props.event.id}/clash-requests`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
