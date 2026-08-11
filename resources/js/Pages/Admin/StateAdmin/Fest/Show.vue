<template>
    <AdminLayout :title="event.name">
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-xl font-semibold">{{ event.name }}</h1>
            <p class="text-sm text-slate-500">Program {{ event.state_program_id }} · {{ event.status }}</p>

            <Link :href="`/admin/state-workspace/fest/${event.id}/attendance`" class="inline-block text-sm text-indigo-600">Mark attendance →</Link>

            <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/60 flex items-center justify-between gap-3">
                <p class="text-xs text-amber-900">
                    Assign chest numbers (101+) to every approved registration's participants who don't have one yet. Safe to run again later — already-numbered participants are skipped.
                </p>
                <button type="button" class="btn-secondary text-xs !py-2 shrink-0 !bg-white hover:!bg-amber-100 !border-amber-300 !text-amber-900"
                        :disabled="chestForm.processing" @click="assignChestNumbers">
                    Assign chest numbers
                </button>
            </div>

            <h2 class="section-title">Judges</h2>
            <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/80 space-y-3">
                <form @submit.prevent="assignJudge" class="flex flex-wrap items-center gap-2">
                    <input v-model="judgeForm.item_code" placeholder="Item code" class="field !py-1 !text-xs w-28" required>
                    <input v-model="judgeForm.item_id" placeholder="Item UUID (from catalog)" class="field !py-1 !text-xs w-56" required>
                    <input v-model="judgeForm.user_email" type="email" placeholder="Judge's account email" class="field !py-1 !text-xs w-56" required>
                    <button type="submit" class="btn-secondary !py-1 !px-3 text-xs" :disabled="judgeForm.processing">Assign judge</button>
                </form>
                <ul v-if="judgeAssignments?.length" class="text-xs divide-y divide-slate-200">
                    <li v-for="a in judgeAssignments" :key="a.id" class="py-1.5 flex items-center justify-between gap-2">
                        <span><span class="font-mono">{{ a.item_code }}</span> — {{ a.user?.name }} ({{ a.user?.email }})</span>
                        <button type="button" class="text-red-600" @click="unassignJudge(a)">Remove</button>
                    </li>
                </ul>
                <p v-else class="text-xs text-slate-400">No judges assigned yet. Once assigned, a judge signs in and enters marks at <span class="font-mono">/portal/state-judge</span> — every assigned judge's scores are averaged into the final mark automatically.</p>
            </div>

            <h2 class="section-title">State registrations</h2>
            <table class="w-full text-sm border" v-if="registrations?.length">
                <thead>
                    <tr class="text-left bg-slate-50">
                        <th class="p-2">Sl No</th>
                        <th class="p-2">Chest No</th>
                        <th class="p-2">Item</th>
                        <th class="p-2">Participant</th>
                        <th class="p-2">School</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Mark (coordinator entry)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(registration, idx) in registrations" :key="registration.id" class="border-t">
                        <td class="p-2">{{ idx + 1 }}</td>
                        <td class="p-2 font-mono">{{ registration.participants?.[0]?.chest_number || '—' }}</td>
                        <td class="p-2">{{ registration.item_code }}</td>
                        <td class="p-2">{{ registration.participants?.[0]?.student_name || 'Participant' }}</td>
                        <td class="p-2">{{ (registration.school_name || '').toUpperCase() || registration.school_id }}</td>
                        <td class="p-2">{{ registration.status }}</td>
                        <td class="p-2" v-if="registration.participants?.[0]">
                            <form class="flex items-center gap-1" @submit.prevent="enterMark(registration.participants[0])">
                                <input v-model="markForms[registration.participants[0].id].score" type="number" step="0.01" min="0" placeholder="Score" class="field !py-1 !text-xs w-16">
                                <select v-model="markForms[registration.participants[0].id].grade" class="field !py-1 !text-xs w-16">
                                    <option value="">Grade</option>
                                    <option value="A+">A+</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                </select>
                                <button type="submit" class="btn-secondary !py-1 !px-2 text-xs">Save</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No materialized state registrations yet. Approve a qualifier intake to populate this event.</p>

            <h2 class="section-title">Approved qualifiers</h2>
            <ul class="text-sm space-y-1">
                <li v-for="q in approvedQualifiers" :key="q.id">{{ q.item_code }} — {{ q.student_name }} ({{ q.school_id }})</li>
            </ul>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    event: Object,
    approvedQualifiers: Array,
    registrations: Array,
    judgeAssignments: { type: Array, default: () => [] },
});

const chestForm = useForm({});
const judgeForm = useForm({ item_id: '', item_code: '', user_email: '' });

const markForms = reactive({});
for (const registration of props.registrations || []) {
    const p = registration.participants?.[0];
    if (p) {
        markForms[p.id] = { score: '', grade: '' };
    }
}

function assignChestNumbers() {
    chestForm.post(`/admin/state-workspace/fest/${props.event.id}/assign-chest-numbers`, { preserveScroll: true });
}

function assignJudge() {
    judgeForm.post(`/admin/state-workspace/fest/${props.event.id}/judges`, {
        preserveScroll: true,
        onSuccess: () => judgeForm.reset(),
    });
}

function unassignJudge(assignment) {
    if (!confirm('Remove this judge assignment?')) return;
    router.delete(`/admin/state-workspace/fest/${props.event.id}/judges/${assignment.id}`, { preserveScroll: true });
}

function enterMark(participant) {
    router.post(`/admin/state-workspace/fest/${props.event.id}/marks`, {
        participant_id: participant.id,
        score: markForms[participant.id].score || null,
        grade: markForms[participant.id].grade || null,
    }, { preserveScroll: true });
}
</script>
