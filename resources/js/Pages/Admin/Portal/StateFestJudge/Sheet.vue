<template>
    <PortalLayout role-label="State Judge" :title="item.title || item.code" accent="amber" :nav-items="[]">
        <div class="space-y-4">
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">{{ item.title || item.code }}</h2>
                        <p class="text-xs text-slate-500">{{ event.name }} <span v-if="item.code" class="font-mono">· {{ item.code }}</span></p>
                    </div>
                    <Link :href="actionUrls.back" class="text-xs font-semibold text-indigo-600">← All panels</Link>
                </div>

                <p v-if="submittedAt" class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    Submitted {{ submittedAt }}. Changing a score here reopens the sheet, so the State
                    office can see it moved.
                </p>
                <p v-else-if="event.scoring_locked" class="mt-2 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    Scoring is locked for this event. Nothing can be changed.
                </p>
                <p v-else class="mt-2 text-xs text-slate-500">
                    Scores are out of {{ marks.max_score }}. You see only your own — panel members do
                    not see each other's marks before they are combined.
                </p>
            </div>

            <div v-if="!targets.length" class="card text-sm text-slate-400">Nothing to score: no approved entries for this item.</div>

            <div v-for="row in targets" :key="row.participant_id" class="card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-lg font-bold text-slate-900">
                            {{ row.chest_number || '—' }}
                            <span v-if="!row.chest_number" class="ml-1 align-middle text-[10px] font-bold uppercase tracking-wide text-amber-600">no chest number</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <span v-if="row.team_size > 1">Team of {{ row.team_size }}</span>
                            <span v-else>Individual</span>
                            <span v-if="row.score !== null && row.score !== undefined"> · scored {{ row.score }}</span>
                        </p>
                    </div>

                    <form class="flex items-end gap-2" @submit.prevent="save(row)">
                        <label>
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-slate-400">Score</span>
                            <input v-model="drafts[row.participant_id].score" type="number" step="0.01"
                                   :min="marks.min_score" :max="marks.max_score"
                                   class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"
                                   :disabled="event.scoring_locked" required>
                        </label>
                        <label>
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-slate-400">Grade</span>
                            <input v-model="drafts[row.participant_id].grade" class="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                   :disabled="event.scoring_locked">
                        </label>
                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white"
                                :disabled="event.scoring_locked">Save</button>
                    </form>
                </div>
            </div>

            <div v-if="targets.length && !event.scoring_locked" class="card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ scored }} of {{ targets.length }} scored</p>
                        <p class="text-xs text-slate-500">
                            A sheet can only be submitted once every entry has a score — a missing one
                            would be averaged as if the judge had never been on the panel.
                        </p>
                    </div>
                    <button type="button" class="rounded-xl bg-[color:var(--brand-navy,#1e3a5f)] px-4 py-2 text-xs font-bold text-white"
                            :disabled="scored < targets.length" @click="submit()">
                        Submit sheet
                    </button>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    event: Object, item: Object, targets: Array, submittedAt: String,
    marks: Object, actionUrls: Object,
});

const drafts = reactive(Object.fromEntries(
    props.targets.map((t) => [t.participant_id, { score: t.score ?? '', grade: t.grade ?? '' }]),
));

const scored = computed(() => props.targets.filter((t) => t.score !== null && t.score !== undefined).length);

function save(row) {
    const draft = drafts[row.participant_id];
    router.post(props.actionUrls.score, {
        participant_id: row.participant_id,
        score: Number(draft.score),
        grade: draft.grade || null,
    }, { preserveScroll: true });
}

function submit() {
    if (!confirm('Submit this sheet? The State office will treat it as final.')) return;
    router.post(props.actionUrls.submit);
}
</script>
