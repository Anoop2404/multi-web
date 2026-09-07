<template>
    <SchoolAdminLayout :title="`${event.title} — Appeals`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`${event.title} — Appeals`"
            eyebrow="Fest Hub"
            description="Track appeal status and submit new requests for result corrections."
        >
            <template #actions>
                <Link :href="`/school-admin/${school.id}/fest/hub`" class="btn-secondary text-sm">← Fest hub</Link>
            </template>
        </PageHeader>

        <div v-if="!event.appeals_open" class="notice-banner notice-banner--warning mb-4 text-sm">
            Appeals are closed for this event. Contact Sahodaya if you need assistance.
        </div>

        <div class="card mb-6">
            <h3 class="section-title mb-1">Submit an appeal</h3>
            <p class="section-desc mb-4">
                Request a review if a participant result needs correction.
                <span v-if="event.appeal_fee_amount && Number(event.appeal_fee_amount) > 0">
                    Appeal fee: ₹{{ event.appeal_fee_amount }} (pay Sahodaya before resolution).
                </span>
            </p>
            <form @submit.prevent="submitAppeal" class="space-y-4 max-w-xl">
                <FormField label="Appeal type" required>
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="appealForm.appeal_type"
                            :options="appealTypeOptions"
                            :all-option="false"
                            :required="true"
                        />
                    </template>
                </FormField>

                <template v-if="appealForm.appeal_type === 'dispute'">
                    <FormField label="Participant" :error="appealForm.errors.participant_id" required>
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="appealForm.participant_id"
                                :options="participantOptions"
                                :all-option="true"
                                all-label="Select participant"
                                :required="true"
                            />
                        </template>
                    </FormField>
                </template>
                <template v-else>
                    <p class="text-xs text-slate-500 -mt-1">
                        Use this when a student didn't get a slot in the normal way — e.g. wasn't
                        selected by this school for the Sahodaya round, or competed at Sahodaya
                        level but didn't qualify for State. The Sahodaya committee reviews and
                        grants (or rejects) the slot; it isn't automatic.
                    </p>
                    <FormField label="Student" :error="appealForm.errors.student_id" required>
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="appealForm.student_id"
                                :options="studentOptions"
                                :all-option="true"
                                all-label="Select student"
                                :required="true"
                            />
                        </template>
                    </FormField>
                    <FormField label="Item" :error="appealForm.errors.item_id" required>
                        <template #default="{ id }">
                            <SearchableSelect
                                :id="id"
                                v-model="appealForm.item_id"
                                :options="itemOptions"
                                :all-option="true"
                                all-label="Select item"
                                :required="true"
                            />
                        </template>
                    </FormField>
                </template>

                <FormField label="Reason for appeal" :error="appealForm.errors.reason" required>
                    <template #default="{ id }">
                        <textarea :id="id" v-model="appealForm.reason" class="field h-24" placeholder="Describe the issue" required></textarea>
                    </template>
                </FormField>
                <button type="submit" class="btn-primary" :disabled="appealForm.processing || !event.appeals_open">
                    {{ appealForm.processing ? 'Submitting…' : 'Submit appeal' }}
                </button>
            </form>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title">Your appeals</h3>
                <p class="section-desc mt-0.5">{{ appeals.length }} total</p>
            </div>
            <div v-if="appeals.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Participant</th>
                            <th class="p-3">Item</th>
                            <th class="p-3">Reason</th>
                            <th class="p-3">Fee</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in appeals" :key="a.id" class="border-t align-top">
                            <td class="p-3 font-medium">
                                {{ participantName(a) }}
                                <p v-if="participantIdNumber(a)" class="text-xs font-mono text-[#0f3d7a]">{{ participantIdNumber(a) }}</p>
                                <span v-if="wildcardLabel(a)" class="inline-block mt-1 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">{{ wildcardLabel(a) }}</span>
                            </td>
                            <td class="p-3 text-xs">{{ a.participant?.registration?.item?.title ?? a.item?.title ?? '—' }}</td>
                            <td class="p-3 text-xs max-w-xs">
                                <p>{{ a.reason }}</p>
                                <p v-if="a.resolution_note" class="text-slate-500 mt-1 italic">Note: {{ a.resolution_note }}</p>
                            </td>
                            <td class="p-3 text-xs whitespace-nowrap">
                                <template v-if="a.fee_amount != null && Number(a.fee_amount) > 0">
                                    ₹{{ a.fee_amount }}
                                    <span v-if="a.fee_paid_at" class="text-emerald-700 block">Paid</span>
                                    <span v-else class="text-amber-700 block">Pending payment</span>
                                </template>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="p-3">
                                <span :class="statusClass(a.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ a.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="p-8 text-center text-gray-400 text-sm">No appeals submitted yet.</p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { studentDisplayName } from '@/support/studentDisplay.js';

const props = defineProps({
    school: Object,
    event: Object,
    appeals: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
});

const appealForm = useForm({ appeal_type: 'dispute', participant_id: '', student_id: '', item_id: '', reason: '' });

const appealTypeOptions = [
    { value: 'dispute', label: 'Result dispute (existing participant)' },
    { value: 'sahodaya_wildcard', label: 'Wildcard — request a Sahodaya-level slot' },
    { value: 'state_wildcard', label: 'Wildcard — request a State-level slot' },
];

const WILDCARD_LABELS = {
    sahodaya_wildcard: 'Wildcard → Sahodaya',
    state_wildcard: 'Wildcard → State',
};

function wildcardLabel(a) {
    return WILDCARD_LABELS[a.appeal_type] ?? null;
}

const allParticipants = computed(() =>
    props.registrations.flatMap(r => (r.participants ?? []).map(p => ({ ...p, registration: r })))
);

const participantOptions = computed(() =>
    allParticipants.value.map(p => ({
        value: p.id,
        label: `${participantLabel(p)} — ${p.registration?.item?.title}`,
    }))
);

const studentOptions = computed(() =>
    props.students.map(s => ({ value: s.id, label: `${studentDisplayName(s)}${s.reg_no ? ' — ' + s.reg_no : ''}` }))
);

const itemOptions = computed(() =>
    props.items.map(i => ({ value: i.id, label: i.title }))
);

function participantLabel(p) {
    return p.student ? studentDisplayName(p.student) : (p.teacher?.name ?? 'Participant');
}

function participantName(a) {
    if (a.appeal_type && a.appeal_type !== 'dispute') {
        return a.student ? studentDisplayName(a.student) : 'Participant';
    }
    return participantLabel(a.participant ?? a);
}

function participantIdNumber(a) {
    const student = (a.participant ?? a).student ?? a.student;
    return student?.admission_number || student?.reg_no || null;
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function submitAppeal() {
    appealForm.transform((data) => data.appeal_type === 'dispute'
        ? { appeal_type: data.appeal_type, participant_id: data.participant_id, reason: data.reason }
        : { appeal_type: data.appeal_type, student_id: data.student_id, item_id: data.item_id, reason: data.reason })
        .post(`/school-admin/${props.school.id}/fest/${props.event.id}/appeals`, {
            preserveScroll: true,
            onSuccess: () => appealForm.reset(),
        });
}
</script>
