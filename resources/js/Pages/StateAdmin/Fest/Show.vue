<template>
    <AdminLayout :title="event.name">
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-xl font-semibold">{{ event.name }}</h1>
            <p class="text-sm text-slate-500">Program {{ event.state_program_id }} · {{ event.status }}</p>

            <div v-if="event.results_published" class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50 text-sm text-emerald-900">
                Results are published and scoring is locked. The public result page now reads these State-final marks.
            </div>

            <Link :href="actionUrls.attendance" class="inline-block text-sm text-indigo-600">Mark attendance →</Link>

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
                                <input v-model="markForms[registration.participants[0].id].score" type="number" step="0.01" min="0" placeholder="Score" class="field !py-1 !text-xs w-16" :disabled="event.scoring_locked">
                                <SearchableSelect v-model="markForms[registration.participants[0].id].grade" class="w-16" :options="[{ value: 'A+', label: 'A+' }, { value: 'A', label: 'A' }, { value: 'B', label: 'B' }, { value: 'C', label: 'C' }]" all-label="Grade" :disabled="event.scoring_locked" />
                                <button type="submit" class="btn-secondary !py-1 !px-2 text-xs" :disabled="event.scoring_locked">Save</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No materialized state registrations yet. Approve a qualifier intake to populate this event.</p>

            <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="section-title !mb-0">State-final results</h2>
                        <p class="text-xs text-slate-500">Positions are calculated from State marks only. Publishing permanently locks scoring.</p>
                    </div>
                    <button v-if="!event.results_published" type="button" class="btn-primary text-xs" :disabled="publishForm.processing" @click="publishResults">
                        Calculate & publish results
                    </button>
                    <a v-else href="/state/results" target="_blank" rel="noopener" class="btn-secondary text-xs">Open public results</a>
                </div>

                <table v-if="publishedResults?.length" class="w-full text-xs border">
                    <thead><tr class="bg-slate-50 text-left"><th class="p-2">Item</th><th class="p-2">Position</th><th class="p-2">Participant</th><th class="p-2">School</th><th class="p-2">Grade</th></tr></thead>
                    <tbody><tr v-for="row in publishedResults" :key="`${row.item_code}-${row.chest_number}`" class="border-t"><td class="p-2">{{ row.item_code }}</td><td class="p-2 font-bold">{{ row.position }}</td><td class="p-2">{{ row.student_name }}</td><td class="p-2">{{ row.school_name }}</td><td class="p-2">{{ row.grade || '—' }}</td></tr></tbody>
                </table>
            </div>

            <div v-if="schoolRankings?.length" class="p-4 rounded-xl border border-slate-200 bg-white space-y-2">
                <h2 class="section-title !mb-0">Top schools / Sahodaya final ranking</h2>
                <ol class="text-sm divide-y divide-slate-100">
                    <li v-for="(row, index) in schoolRankings" :key="row.school_id" class="py-2 flex justify-between"><span><strong>#{{ index + 1 }}</strong> {{ row.school_name }}</span><span class="font-bold">{{ row.points }} points</span></li>
                </ol>
            </div>

            <h2 class="section-title">Approved qualifiers</h2>
            <ul class="text-sm space-y-1">
                <li v-for="q in approvedQualifiers" :key="q.id">{{ q.item_code }} — {{ q.student_name }} ({{ q.school_id }})</li>
            </ul>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    event: Object,
    approvedQualifiers: Array,
    registrations: Array,
    judgeAssignments: { type: Array, default: () => [] },
    publishedResults: { type: Array, default: () => [] },
    schoolRankings: { type: Array, default: () => [] },
    actionUrls: { type: Object, required: true },
});

const chestForm = useForm({});
const publishForm = useForm({});
const judgeForm = useForm({ item_id: '', item_code: '', user_email: '' });

const markForms = reactive({});
for (const registration of props.registrations || []) {
    const p = registration.participants?.[0];
    if (p) {
        markForms[p.id] = { score: p.mark?.score ?? '', grade: p.mark?.grade ?? '' };
    }
}

function assignChestNumbers() {
    chestForm.post(props.actionUrls.chestNumbers, { preserveScroll: true });
}

function assignJudge() {
    judgeForm.post(props.actionUrls.judges, {
        preserveScroll: true,
        onSuccess: () => judgeForm.reset(),
    });
}

async function unassignJudge(assignment) {
    if (!(await confirm({ message: 'Remove this judge assignment?', destructive: true }))) return;
    router.delete(`${props.actionUrls.judges}/${assignment.id}`, { preserveScroll: true });
}

function enterMark(participant) {
    router.post(props.actionUrls.marks, {
        participant_id: participant.id,
        score: markForms[participant.id].score || null,
        grade: markForms[participant.id].grade || null,
    }, { preserveScroll: true });
}

async function publishResults() {
    if (!(await confirm({ message: 'Calculate positions from State-final marks and publish? Scoring will be locked.', destructive: false }))) return;
    publishForm.post(props.actionUrls.publishResults, { preserveScroll: true });
}
</script>
