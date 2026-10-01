<template>
    <SchoolAdminLayout :title="`Clash reports — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Schedule clash reports`" :eyebrow="programLabel"
                    description="All schedule clashes detected for your participants are listed below as active clash requests.">
            <template #actions>
                <a :href="printFormUrl()" target="_blank" rel="noopener" class="btn-secondary text-sm">🖨️ Print blank clash form</a>
                <button type="button" @click="showManualForm = !showManualForm" class="btn-secondary text-sm">
                    {{ showManualForm ? 'Hide manual form' : '+ Report manual clash' }}
                </button>
                <Link :href="`${programBase}/registration?event=${event.id}`" class="btn-secondary text-sm">← Registration</Link>
            </template>
        </PageHeader>

        <!-- Status notice banner -->
        <div v-if="requests.length === 0" class="notice-banner notice-banner--success mb-6">
            ✅ No schedule clashes detected for your school participants. The timetable has no overlapping slots for your students.
        </div>
        <div v-else class="notice-banner notice-banner--warning mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <strong>⚠️ {{ requests.length }} schedule clash report(s) active for your school.</strong>
                <p class="text-xs text-amber-900 mt-0.5">These clashes are automatically detected and logged as requests for Sahodaya review. Print the official clash form to present at the desk, or add specific resolution notes below.</p>
            </div>
            <a :href="`${programBase}/reports/${event.id}/schedule-clashes`" class="text-xs font-semibold underline text-amber-950 whitespace-nowrap">
                View clash matrix report →
            </a>
        </div>

        <!-- Optional collapsible manual form -->
        <div v-if="showManualForm" class="card mb-6 max-w-2xl space-y-3 border-2 border-indigo-100 bg-indigo-50/30">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Report a manual / custom clash</h3>
                <button type="button" @click="showManualForm = false" class="text-xs text-slate-500 hover:text-slate-800">✕ Close</button>
            </div>
            <p class="text-xs text-slate-600">Use this only if you need to report a special conflict that wasn't automatically detected above.</p>
            <SearchableSelect v-model="form.participant_id" :options="participantOptions" :all-option="true"
                              all-label="Select participant" :required="true" @change="onParticipantChange" />
            <div v-if="participantSchedules.length">
                <p class="text-xs font-semibold text-slate-600 mb-1.5">
                    Clashing schedule slots — tick every slot that overlaps (two or more)
                </p>
                <div class="space-y-1.5">
                    <label v-for="s in participantSchedules" :key="s.id"
                           class="flex items-center gap-2 text-sm rounded border px-2.5 py-1.5 cursor-pointer bg-white"
                           :class="form.schedule_ids.includes(s.id) ? 'border-indigo-500 bg-indigo-50 font-medium' : 'border-slate-200'">
                        <input type="checkbox" :value="s.id" v-model="form.schedule_ids" class="rounded" />
                        <span>
                            {{ s.item_title }}<template v-if="s.category_label"> ({{ s.category_label }})</template>
                            <span class="text-slate-500 text-xs">· {{ s.stage || 'Stage' }} · {{ s.time || formatTime(s.scheduled_at) }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="form.schedule_ids.length === 1" class="text-xs text-amber-600 mt-1">Tick at least one more slot — a clash needs two or more.</p>
            </div>
            <textarea v-model="form.description" class="field text-sm" rows="2" placeholder="Describe the clash" required />
            <textarea v-model="form.requested_resolution" class="field text-sm" rows="2" placeholder="Suggested resolution (optional)" />
            <div class="flex gap-2">
                <button type="button" @click="submit" class="btn-primary text-sm" :disabled="form.processing || form.schedule_ids.length < 2">
                    Submit manual report
                </button>
                <button type="button" @click="showManualForm = false" class="btn-secondary text-sm">Cancel</button>
            </div>
        </div>

        <!-- Clash requests table -->
        <div class="card overflow-x-auto p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width: 170px;">Participant</th>
                        <th style="min-width: 280px;">Clashing items</th>
                        <th style="min-width: 240px;">Description &amp; Resolution</th>
                        <th style="min-width: 110px;">Status</th>
                        <th style="min-width: 110px;" class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests" :key="r.id" class="align-top">
                        <td>
                            <p class="font-bold text-slate-900 text-sm">
                                {{ r.student_name || (r.participant?.student ? studentDisplayName(r.participant.student) : '—') }}
                            </p>
                            <p v-if="r.roll_no" class="text-xs font-semibold text-indigo-700 mt-0.5">
                                Roll / Fest ID: {{ r.roll_no }}
                            </p>
                            <p v-if="r.participant?.group?.name" class="text-xs text-slate-500">
                                {{ r.participant.group.name }}
                            </p>
                        </td>
                        <td>
                            <div v-if="r.schedules?.length" class="space-y-1.5">
                                <div v-for="s in r.schedules" :key="s.id"
                                     class="rounded border border-slate-200 bg-slate-50/70 p-2 text-xs">
                                    <div class="font-bold text-slate-900 text-sm">{{ s.item_title }}</div>
                                    <div class="text-slate-600 mt-0.5 flex flex-wrap gap-x-2">
                                        <span v-if="s.stage" class="font-medium text-slate-700">📍 {{ s.stage }}</span>
                                        <span v-if="s.time" class="font-semibold text-indigo-800">⏰ {{ s.time }}</span>
                                        <span v-else-if="s.date" class="text-slate-500">📅 {{ s.date }}</span>
                                    </div>
                                </div>
                            </div>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="text-sm">
                            <p class="text-slate-800">{{ r.description }}</p>

                            <div v-if="r.requested_resolution" class="mt-2 rounded bg-indigo-50 border border-indigo-100 p-2 text-xs">
                                <span class="font-semibold text-indigo-900">Requested Resolution:</span>
                                <p class="text-indigo-800 mt-0.5">{{ r.requested_resolution }}</p>
                            </div>

                            <div v-if="r.resolution_note" class="mt-2 rounded bg-emerald-50 border border-emerald-200 p-2 text-xs">
                                <span class="font-bold text-emerald-900">Sahodaya Official Note:</span>
                                <p class="text-emerald-800 mt-0.5">{{ r.resolution_note }}</p>
                            </div>

                            <button type="button" @click="openSuggestionModal(r)"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 hover:underline mt-2 inline-flex items-center gap-1">
                                <span>✏️ {{ r.requested_resolution ? 'Edit resolution suggestion' : '+ Add resolution suggestion' }}</span>
                            </button>
                        </td>
                        <td>
                            <span :class="statusClass(r.status)" class="text-xs font-bold px-2 py-0.5 rounded uppercase tracking-wider">{{ r.status }}</span>
                            <p v-if="r.reviewed_at" class="text-[11px] text-slate-500 mt-1">Reviewed {{ formatTime(r.reviewed_at) }}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Submitted {{ formatTime(r.created_at) }}</p>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a :href="printFormUrl(r.id)" target="_blank" rel="noopener"
                               class="btn-secondary text-xs !py-1.5 !px-3 inline-flex items-center gap-1.5 font-bold text-indigo-700 hover:text-indigo-900 hover:bg-indigo-50 border-indigo-300 shadow-sm"
                               title="Generate official 2-copy printable clash form">
                                <span>🖨️ Print form</span>
                            </a>
                        </td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td colspan="5" class="text-center text-slate-400 py-10">
                            No schedule clashes found. If overlapping timings occur, they will automatically appear here.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Suggestion Modal -->
        <div v-if="editingRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="card w-full max-w-lg bg-white shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="font-bold text-slate-900">Suggested resolution for Sahodaya</h3>
                    <button type="button" @click="editingRequest = null" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>
                <p class="text-xs text-slate-600">
                    Suggest an alternate timing, slot swap, or stage arrangement for
                    <strong>{{ editingRequest.student_name || 'this student' }}</strong>.
                </p>
                <textarea v-model="suggestionText" rows="4" class="field text-sm w-full"
                          placeholder="e.g. Requesting to shift Recitation to afternoon session or swap with item after 2:00 PM." />
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" @click="editingRequest = null" class="btn-secondary text-sm">Cancel</button>
                    <button type="button" @click="saveSuggestion" class="btn-primary text-sm" :disabled="isSaving">
                        Save suggestion
                    </button>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
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
    detectedCount: { type: Number, default: 0 },
});

const { programLabel, programBase } = useSchoolProgramContext(props);

const showManualForm = ref(false);
const editingRequest = ref(null);
const suggestionText = ref('');
const isSaving = ref(false);

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
    if (!value) return '—';
    const d = new Date(value);
    return isNaN(d) ? value : d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800 border border-amber-300',
        approved: 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        resolved: 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        rejected: 'bg-red-100 text-red-700 border border-red-300',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function submit() {
    form.post(`${programBase.value}/events/${props.event.id}/clash-requests`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showManualForm.value = false;
        },
    });
}

function openSuggestionModal(r) {
    editingRequest.value = r;
    suggestionText.value = r.requested_resolution || '';
}

function saveSuggestion() {
    if (!editingRequest.value) return;
    isSaving.value = true;
    router.patch(
        `${programBase.value}/events/${props.event.id}/clash-requests/${editingRequest.value.id}`,
        { requested_resolution: suggestionText.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isSaving.value = false;
                editingRequest.value = null;
            },
        }
    );
}

function printFormUrl(clashRequestId = null) {
    const base = `${programBase.value}/events/${props.event.id}/clash-requests/print-form`;
    return clashRequestId ? `${base}?clash_request=${clashRequestId}` : `${base}?preview=1`;
}
</script>
