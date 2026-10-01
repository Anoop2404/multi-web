<template>
    <SchoolAdminLayout :title="`Clash reports — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Schedule clash reports`" :eyebrow="programLabel"
                    description="Report overlapping schedules for your participants.">
            <template #actions>
                <a :href="printFormUrl()" target="_blank" rel="noopener" class="btn-secondary text-sm">🖨️ Print blank clash form</a>
                <Link :href="`${programBase}/reports/${event.id}/schedule-clashes`" class="btn-secondary text-sm">Detected clashes</Link>
                <Link :href="`${programBase}/registration?event=${event.id}`" class="btn-secondary text-sm">← Registration</Link>
            </template>
        </PageHeader>

        <form class="card mb-6 max-w-2xl space-y-3" @submit.prevent="submit">
            <h3 class="font-semibold text-slate-900">Report a clash</h3>
            <SearchableSelect v-model="form.participant_id" :options="participantOptions" :all-option="true"
                              all-label="Select participant" :required="true" @change="onParticipantChange" />
            <div v-if="participantSchedules.length">
                <p class="text-xs font-semibold text-slate-600 mb-1.5">
                    Clashing schedule slots — tick every slot that overlaps (two or more)
                </p>
                <div class="space-y-1.5">
                    <label v-for="s in participantSchedules" :key="s.id"
                           class="flex items-center gap-2 text-sm rounded border px-2.5 py-1.5 cursor-pointer"
                           :class="form.schedule_ids.includes(s.id) ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'">
                        <input type="checkbox" :value="s.id" v-model="form.schedule_ids" class="rounded" />
                        <span>{{ s.item_title }}<template v-if="s.category_label"> ({{ s.category_label }})</template> · {{ formatTime(s.scheduled_at) }}</span>
                    </label>
                </div>
                <p v-if="form.schedule_ids.length === 1" class="text-xs text-amber-600 mt-1">Tick at least one more slot — a clash needs two or more.</p>
            </div>
            <textarea v-model="form.description" class="field text-sm" rows="3" placeholder="Describe the clash" required />
            <textarea v-model="form.requested_resolution" class="field text-sm" rows="2" placeholder="Suggested resolution (optional)" />
            <button type="submit" class="btn-primary text-sm" :disabled="form.processing || form.schedule_ids.length < 2">Submit report</button>
        </form>

        <div class="card overflow-x-auto p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Clashing items</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests" :key="r.id">
                        <td>{{ r.participant?.student ? studentDisplayName(r.participant.student) : '—' }}</td>
                        <td class="text-sm">
                            <ul v-if="r.schedules?.length" class="list-disc list-inside">
                                <li v-for="s in r.schedules" :key="s.id">{{ s.item_title }}</li>
                            </ul>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="text-sm">
                            <p>{{ r.description }}</p>
                            <p v-if="r.resolution_note" class="text-slate-500 mt-1 italic">Note: {{ r.resolution_note }}</p>
                        </td>
                        <td>
                            <span :class="statusClass(r.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ r.status }}</span>
                            <p v-if="r.reviewed_at" class="text-[11px] text-slate-400 mt-1">Reviewed {{ formatTime(r.reviewed_at) }}</p>
                        </td>
                        <td class="text-xs">{{ r.created_at ? new Date(r.created_at).toLocaleString() : '—' }}</td>
                        <td class="text-right">
                            <a :href="printFormUrl(r.id)" target="_blank" rel="noopener" class="text-xs font-semibold text-indigo-600 hover:underline">🖨️ Print</a>
                        </td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td colspan="6" class="text-center text-slate-400 py-8">No clash reports yet.</td>
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
    schedule_ids: [],
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

function onParticipantChange() {
    form.schedule_ids = [];
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
    form.post(`${programBase.value}/events/${props.event.id}/clash-requests`, { preserveScroll: true, onSuccess: () => form.reset() });
}

// Printable "Off Stage/Stage Events — Clash Form", branded with this Sahodaya's own
// header — blank (for filling in by hand) with no id, or pre-filled from an already
// filed report when given one.
function printFormUrl(clashRequestId = null) {
    const base = `${programBase.value}/events/${props.event.id}/clash-requests/print-form`;
    return clashRequestId ? `${base}?clash_request=${clashRequestId}` : `${base}?preview=1`;
}
</script>
