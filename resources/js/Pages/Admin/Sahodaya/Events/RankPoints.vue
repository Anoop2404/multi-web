<template>
    <SahodayaEventsLayout :title="`${event.title} — ${isSports ? 'Rank Points' : 'Grade Points Master'}`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — ${isSports ? 'Rank Points' : 'Grade Points Master'}`" :eyebrow="isSports ? 'Rank points' : 'Grade points master'"
                    description="Championship points awarded per rank position or grade, used for the leaderboard.">
            <template #actions>
                <button type="button" class="btn-secondary text-sm" :disabled="recalculating" @click="recalculateMarks">
                    {{ recalculating ? 'Recalculating…' : '🔄 Recalculate all marks' }}
                </button>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="rank-points" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="rank-points" class="mb-4" />

        <div v-if="childEvents.length" class="card !p-4 mb-5 flex flex-wrap items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ isSports ? 'Sport Event / Region:' : 'Phase / Region:' }}</label>
            <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent" :options="childEventOptions" :all-option="false" class="w-64" />
        </div>

        <div class="space-y-6">
            <div v-if="isSports" class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-sm space-y-1.5">
                <p class="font-bold text-indigo-900 flex items-center gap-1.5 text-sm">
                    <span>🏆</span> Sports Rank Points Master
                </p>
                <p class="text-indigo-900/80 leading-relaxed">
                    Set fixed championship points for each position (1st, 2nd, 3rd place). Multiple athletes can share
                    the same rank in case of ties or joint positions — each receives full points. Create a named
                    template per points scale and assign it to whichever participant types (individual, pair, trio,
                    group, team) should use it — types left unassigned fall back to whatever template covers
                    "Individual."
                </p>
            </div>

            <section v-if="isSports" class="card !p-5 space-y-4 border border-slate-200">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="form-label text-xs">New template name</label>
                    <input v-model="newTemplateName" type="text" class="field text-xs flex-1 max-w-xs" placeholder="e.g. Relay Standard">
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="!newTemplateName.trim() || creatingTemplate" @click="createTemplate">
                        {{ creatingTemplate ? 'Creating…' : '+ New Template' }}
                    </button>
                </div>
            </section>

            <section v-for="draft in templateDrafts" :key="draft.id" class="card !p-5 space-y-4 border border-slate-200">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex-1 min-w-[14rem] space-y-2">
                        <input v-model="draft.name" type="text" class="field text-sm font-bold w-full max-w-xs" placeholder="Template name">
                        <div class="flex flex-wrap gap-3">
                            <label v-for="type in allParticipantTypes" :key="type" class="flex items-center gap-1.5 text-xs font-medium text-slate-700">
                                <input type="checkbox" :value="type" v-model="draft.participant_types">
                                {{ typeLabel(type) }}
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" class="btn-secondary text-xs !py-1.5 !px-3" :disabled="draft.savingTemplate" @click="saveTemplateMeta(draft)">
                            {{ draft.savingTemplate ? 'Saving…' : 'Save Name & Types' }}
                        </button>
                        <button type="button" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1.5 !px-3" @click="deleteTemplate(draft)">
                            Delete
                        </button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="btn-secondary text-xs !py-1.5 !px-3" :disabled="draft.seeding" @click="seedAthletics(draft)">
                        {{ draft.seeding ? 'Loading...' : '⚡ Load Athletics Standard (8,7,6,5,4,3)' }}
                    </button>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th class="p-3.5 w-44">Rank Position</th>
                                <th class="p-3.5">Championship Points</th>
                                <th class="p-3.5 text-right w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in draft.rows" :key="row._key" class="hover:bg-slate-50/70 transition">
                                <td class="p-3.5 font-bold text-slate-900">{{ rankLabel(row.rank) }}</td>
                                <td class="p-3.5">
                                    <input v-model.number="row.points" type="number" min="0" class="field text-xs w-32 tabular-nums" required>
                                </td>
                                <td class="p-3.5 text-right">
                                    <button v-if="draft.rows.length > 1" type="button" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5" @click="draft.rows.splice(index, 1)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!draft.rows.length" class="text-center text-slate-400 text-xs p-8">
                        No ranks in this template yet — add one below, or load the athletics standard above.
                    </p>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <button type="button" class="btn-secondary text-xs" @click="addRankRow(draft)">+ Add Rank Position</button>
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="draft.savingPoints" @click="savePoints(draft)">
                        {{ draft.savingPoints ? 'Saving...' : 'Save Points' }}
                    </button>
                </div>
            </section>

            <p v-if="isSports && !templateDrafts.length" class="text-xs text-slate-400 text-center py-6">
                No rank point templates yet — create one above (e.g. "Individual") to get started.
            </p>

            <section v-if="!isSports" class="card !p-5 space-y-4 border border-slate-200">
                <div class="border-b border-slate-100 pb-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                            <span>🏆</span> Grade Points Master
                        </h3>
                        <p class="section-desc mt-0.5">Points awarded by grade and position for leaderboard calculations — set separate rules for group/team items vs. individual items.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button type="button" class="btn-secondary text-xs !py-1.5 !px-3" :disabled="seedingConfed" @click="seedConfedKalotsav">
                            {{ seedingConfed ? 'Loading…' : '⚡ Load Kalolsavam Manual Standard' }}
                        </button>
                        <button v-if="isHubWithRegions" type="button" class="btn-secondary text-xs !py-1.5 !px-3"
                                :disabled="syncingToRegions || !props.pointRules.length" :title="!props.pointRules.length ? 'Add or load points here first' : ''"
                                @click="syncToRegions">
                            {{ syncingToRegions ? 'Syncing…' : `🔄 Sync to All Regions (${regionCount})` }}
                        </button>
                    </div>
                </div>

                <form @submit.prevent="addPointRule" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        {{ editingRuleId ? '✎ Edit Championship Point Rule' : '+ Add Championship Point Rule' }}
                    </h4>
                    <p v-if="matchingExistingRule" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        ⚠️ A rule for this exact combination already awards <strong>{{ matchingExistingRule.points }} pts</strong> — saving will overwrite it.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="form-label text-xs">Grade</label>
                            <SearchableSelect v-model="pointForm.grade" :options="gradeSelectOptions" :all-option="true" all-label="Any Grade" />
                        </div>
                        <div>
                            <label class="form-label text-xs">Position / Rank</label>
                            <SearchableSelect v-model="pointForm.position" :options="[{ value: '1', label: '1st Place' }, { value: '2', label: '2nd Place' }, { value: '3', label: '3rd Place' }]" :all-option="true" all-label="Any Position" />
                        </div>
                        <div>
                            <label class="form-label text-xs">Points Awarded *</label>
                            <input v-model.number="pointForm.points" type="number" min="0" class="field text-xs" required placeholder="5">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700">
                        <input type="checkbox" v-model="pointForm.is_group">
                        Group / team item (unchecked = individual item)
                    </label>
                    <div class="flex justify-end gap-2 pt-1">
                        <button v-if="editingRuleId" type="button" class="btn-secondary text-xs !py-1.5 !px-4" @click="cancelEditPointRule">Cancel</button>
                        <button type="submit" class="btn-primary text-xs !py-1.5 !px-4">{{ editingRuleId ? 'Save changes' : 'Add Point Rule' }}</button>
                    </div>
                </form>

                <div v-if="props.pointRules.length" class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th class="p-3.5">Grade Filter</th>
                                <th class="p-3.5">Position Filter</th>
                                <th class="p-3.5">Type</th>
                                <th class="p-3.5">Points</th>
                                <th class="p-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="rule in props.pointRules" :key="rule.id" class="hover:bg-slate-50/70 transition">
                                <td class="p-3.5 font-bold text-slate-800">{{ rule.grade || 'Any Grade' }}</td>
                                <td class="p-3.5 font-bold text-slate-800">{{ rule.position ? `${rule.position} Place` : 'Any Position' }}</td>
                                <td class="p-3.5 text-slate-600">{{ rule.is_group ? 'Group / team' : 'Individual' }}</td>
                                <td class="p-3.5 font-black text-slate-900 tabular-nums">{{ rule.points }} pts</td>
                                <td class="p-3.5 text-right whitespace-nowrap">
                                    <button type="button" @click="startEditPointRule(rule)" class="btn-secondary text-xs !py-1 !px-2.5 mr-1.5">
                                        Edit
                                    </button>
                                    <button type="button" @click="removePointRule(rule.id)" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
                    No point rules defined yet. Use the form above to add grade and position point rules.
                </div>
            </section>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    pointRules: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    allParticipantTypes: { type: Array, default: () => ['individual', 'pair', 'trio', 'group', 'team'] },
    gradeConfigs: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`);
const isSports = computed(() => props.event?.event_type === 'sports');

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/rank-points`);
}

const childEventOptions = computed(() => (props.childEvents ?? []).map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })));

const recalculating = ref(false);
async function recalculateMarks() {
    const ok = await confirm({
        message: 'Refresh every mark\'s stored grade and score against the current Rank Points / Grade Points Master config? This updates marks already saved — it won\'t touch raw scores a judge entered, only the grade/points derived from them.',
    });
    if (!ok) return;
    recalculating.value = true;
    router.post(`${base.value}/recalculate-marks`, {}, {
        preserveScroll: true,
        onFinish: () => { recalculating.value = false; },
    });
}

// Same "this event's actual grade set" derivation as Grade Master's datalist, just
// rendered as a <select> here since point rules reference an existing grade rather than
// defining a new one. Falls back to the legacy four when nothing's been customized.
const gradeOptions = computed(() => {
    const used = [...new Set((props.gradeConfigs ?? []).map((g) => String(g.grade || '').replace('_plus', '+')).filter(Boolean))];
    return used.length ? used : ['A+', 'A', 'B', 'C'];
});

const gradeSelectOptions = computed(() => gradeOptions.value.map((g) => ({ value: g, label: `Grade ${g}` })));

const pointForm = useForm({ grade: '', position: null, points: null, is_group: false });
const editingRuleId = ref(null);

// (grade, position, is_group) is a rule's real identity on the backend — saving the
// same combination again silently overwrites the existing rule's points rather than
// creating a second, ambiguous one. Surface that before it happens instead of letting
// an admin accidentally overwrite a rule they didn't realize already existed.
const matchingExistingRule = computed(() => (props.pointRules ?? []).find((r) =>
    String(r.grade || '') === String(pointForm.grade || '')
    && String(r.position || '') === String(pointForm.position || '')
    && Boolean(r.is_group) === Boolean(pointForm.is_group)
    && r.id !== editingRuleId.value
));

async function addPointRule() {
    const existing = matchingExistingRule.value;
    if (existing) {
        const ok = await confirm({
            message: `A rule for ${pointForm.grade || 'Any Grade'} / ${pointForm.position ? pointForm.position + ' Place' : 'Any Position'} / ${pointForm.is_group ? 'Group' : 'Individual'} already awards ${existing.points} pts. Save ${pointForm.points} pts instead?`,
        });
        if (!ok) return;
    }
    pointForm.post(`${base.value}/point-rules`, {
        preserveScroll: true,
        onSuccess: () => { pointForm.reset(); editingRuleId.value = null; },
    });
}

function startEditPointRule(rule) {
    editingRuleId.value = rule.id;
    pointForm.grade = rule.grade || '';
    pointForm.position = rule.position || '';
    pointForm.points = rule.points;
    pointForm.is_group = Boolean(rule.is_group);
}

function cancelEditPointRule() {
    editingRuleId.value = null;
    pointForm.reset();
}

async function removePointRule(id) {
    const ok = await confirm({
        message: 'Remove this point rule? Marks that relied on it will fall back to whatever the next-matching rule (or the default table) awards instead.',
        destructive: true,
    });
    if (!ok) return;
    router.delete(`${base.value}/point-rules/${id}`, { preserveScroll: true });
}

const seedingConfed = ref(false);

function seedConfedKalotsav() {
    seedingConfed.value = true;
    router.post(`${base.value}/point-rules/seed-confed-kalotsav`, {}, {
        preserveScroll: true,
        onFinish: () => { seedingConfed.value = false; },
    });
}

// childEvents[0] is always the hub ("All Regions") when regions exist — see
// FestEvent::regionDropdownOptions(). Only offer the sync action from the hub itself,
// since it's the one screen meant to hold the shared/canonical rule set.
const isHubWithRegions = computed(() => props.childEvents.length > 1 && String(props.event.id) === String(props.childEvents[0]?.id));
const regionCount = computed(() => Math.max(props.childEvents.length - 1, 0));

const syncingToRegions = ref(false);

function syncToRegions() {
    syncingToRegions.value = true;
    router.post(`${base.value}/point-rules/sync-to-regions`, {}, {
        preserveScroll: true,
        onFinish: () => { syncingToRegions.value = false; },
    });
}

function rankLabel(r) {
    if (r === 1) return '1st Place';
    if (r === 2) return '2nd Place';
    if (r === 3) return '3rd Place';
    return `${r}th Place`;
}

function typeLabel(type) {
    return type.charAt(0).toUpperCase() + type.slice(1);
}

let draftKeySeq = 0;

function buildDraft(template) {
    return {
        id: template.id,
        name: template.name,
        participant_types: [...(template.participant_types ?? [])],
        rows: (template.points ?? []).map((r) => ({ _key: draftKeySeq++, rank: r.rank, points: r.points })),
        savingTemplate: false,
        savingPoints: false,
        seeding: false,
    };
}

// Local editable copies, one per template — re-synced whenever props.templates changes
// (after any create/save/delete round trip returns fresh data from the server).
const templateDrafts = ref((props.templates ?? []).map(buildDraft));
watch(() => props.templates, (templates) => {
    templateDrafts.value = (templates ?? []).map(buildDraft);
});

const newTemplateName = ref('');
const creatingTemplate = ref(false);

function createTemplate() {
    if (!newTemplateName.value.trim()) return;
    creatingTemplate.value = true;
    router.post(`${base.value}/rank-point-templates`, { name: newTemplateName.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => { newTemplateName.value = ''; },
        onFinish: () => { creatingTemplate.value = false; },
    });
}

function saveTemplateMeta(draft) {
    draft.savingTemplate = true;
    router.put(`${base.value}/rank-point-templates/${draft.id}`, {
        name: draft.name,
        participant_types: draft.participant_types,
    }, {
        preserveScroll: true,
        onFinish: () => { draft.savingTemplate = false; },
    });
}

async function deleteTemplate(draft) {
    const ok = await confirm({
        message: `Delete the "${draft.name}" rank points template? This removes all its rank/points rows. Participant types assigned to it will fall back to the Individual template (or the default table) until reassigned.`,
        destructive: true,
    });
    if (!ok) return;
    router.delete(`${base.value}/rank-point-templates/${draft.id}`, { preserveScroll: true });
}

function addRankRow(draft) {
    const nextRank = draft.rows.length + 1;
    draft.rows.push({ _key: draftKeySeq++, rank: nextRank, points: 0 });
}

function savePoints(draft) {
    draft.savingPoints = true;
    router.put(`${base.value}/rank-point-templates/${draft.id}/points`, {
        ranks: draft.rows.map((row) => ({ rank: row.rank, points: row.points })),
    }, {
        preserveScroll: true,
        onFinish: () => { draft.savingPoints = false; },
    });
}

function seedAthletics(draft) {
    draft.seeding = true;
    router.post(`${base.value}/rank-point-templates/${draft.id}/seed-athletics`, {}, {
        preserveScroll: true,
        onFinish: () => { draft.seeding = false; },
    });
}
</script>
