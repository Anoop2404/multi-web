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
            <select v-model="form.participant_id" class="field text-sm" required @change="onParticipantChange">
                <option value="">Select participant</option>
                <option v-for="p in participants" :key="p.id" :value="p.id">{{ p.name }} — {{ p.item }}</option>
            </select>
            <select v-if="participantSchedules.length" v-model="form.schedule_id_a" class="field text-sm">
                <option value="">Schedule slot A (optional)</option>
                <option v-for="s in participantSchedules" :key="s.id" :value="s.id">{{ s.item_title }} · {{ formatTime(s.scheduled_at) }}</option>
            </select>
            <select v-if="participantSchedules.length" v-model="form.schedule_id_b" class="field text-sm">
                <option value="">Schedule slot B (optional)</option>
                <option v-for="s in participantSchedules" :key="s.id" :value="s.id">{{ s.item_title }} · {{ formatTime(s.scheduled_at) }}</option>
            </select>
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
                        <td>{{ r.participant?.student?.name || '—' }}</td>
                        <td class="text-sm">{{ r.description }}</td>
                        <td><span class="text-xs capitalize">{{ r.status }}</span></td>
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
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

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

function onParticipantChange() {
    form.schedule_id_a = '';
    form.schedule_id_b = '';
}

function formatTime(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function submit() {
    form.post(`${programBase}/events/${props.event.id}/clash-requests`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
