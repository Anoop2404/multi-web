<template>
    <SahodayaEventsLayout :title="`${event.title} — Rank Points`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Rank Points`" eyebrow="Rank points"
                    description="Championship points awarded per rank position or grade, used for the leaderboard." />

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="rank-points" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="rank-points" class="mb-4" />

        <div class="space-y-6">
            <div v-if="isSports" class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-sm space-y-1.5">
                <p class="font-bold text-indigo-900 flex items-center gap-1.5 text-sm">
                    <span>🏆</span> Sports Rank Points Master
                </p>
                <p class="text-indigo-900/80 leading-relaxed">
                    Set fixed championship points for each position (1st, 2nd, 3rd place).
                    Multiple athletes can share the same rank in case of ties or joint positions — each receives full points.
                </p>
            </div>

            <section v-if="isSports" class="card !p-5 space-y-4 border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                            <span>🥇</span> Individual Rank Points Table
                        </h3>
                        <p class="section-desc mt-0.5">Track &amp; field, individual events — rank position to team points.</p>
                    </div>
                    <button type="button" class="btn-secondary text-xs !py-1.5 !px-3" :disabled="seeding" @click="seedAthletics">
                        {{ seeding ? 'Loading...' : '⚡ Load Athletics Standard (5, 3, 1)' }}
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
                            <tr v-for="(row, index) in rankRows" :key="row._key" class="hover:bg-slate-50/70 transition">
                                <td class="p-3.5 font-bold text-slate-900">{{ rankLabel(row.rank) }}</td>
                                <td class="p-3.5">
                                    <input v-model.number="row.points" type="number" min="0" class="field text-xs w-32 tabular-nums" required>
                                </td>
                                <td class="p-3.5 text-right">
                                    <button v-if="rankRows.length > 1" type="button" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5" @click="removeRankRow(index)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <button type="button" class="btn-secondary text-xs" @click="addRankRow">+ Add Rank Position</button>
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="savingRanks" @click="saveRankPoints(false)">
                        {{ savingRanks ? 'Saving...' : 'Save Rank Points Table' }}
                    </button>
                </div>
            </section>

            <section v-if="isSports" class="card !p-5 space-y-4 border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                            <span>👥</span> Team / Relay Rank Points Table
                        </h3>
                        <p class="section-desc mt-0.5">Optional separate table for team games and relays. Leave empty to fallback to individual ranks.</p>
                    </div>
                </div>

                <div v-if="groupRankRows.length" class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th class="p-3.5 w-44">Rank Position</th>
                                <th class="p-3.5">Team Points</th>
                                <th class="p-3.5 text-right w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in groupRankRows" :key="row._key" class="hover:bg-slate-50/70 transition">
                                <td class="p-3.5 font-bold text-slate-900">{{ rankLabel(row.rank) }}</td>
                                <td class="p-3.5">
                                    <input v-model.number="row.points" type="number" min="0" class="field text-xs w-32 tabular-nums" required>
                                </td>
                                <td class="p-3.5 text-right">
                                    <button type="button" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2.5" @click="groupRankRows.splice(index, 1)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400 text-xs">
                    No team rank table configured yet — add rows if team events need custom points.
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <button type="button" class="btn-secondary text-xs" @click="addGroupRankRow">+ Add Team Rank Position</button>
                    <button v-if="groupRankRows.length" type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="savingGroupRanks" @click="saveRankPoints(true)">
                        {{ savingGroupRanks ? 'Saving...' : 'Save Team Rank Table' }}
                    </button>
                </div>
            </section>

            <section v-if="!isSports" class="card !p-5 space-y-4 border border-slate-200">
                <div class="border-b border-slate-100 pb-3">
                    <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                        <span>🏆</span> Grade Points Master
                    </h3>
                    <p class="section-desc mt-0.5">Points awarded by grade and position for leaderboard calculations — set separate rules for group/team items vs. individual items.</p>
                </div>

                <form @submit.prevent="addPointRule" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">+ Add Championship Point Rule</h4>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="form-label text-xs">Grade</label>
                            <select v-model="pointForm.grade" class="field text-xs">
                                <option value="">Any Grade</option>
                                <option v-for="g in gradeOptions" :key="g" :value="g">Grade {{ g }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label text-xs">Position / Rank</label>
                            <select v-model="pointForm.position" class="field text-xs">
                                <option value="">Any Position</option>
                                <option value="1">1st Place</option>
                                <option value="2">2nd Place</option>
                                <option value="3">3rd Place</option>
                            </select>
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
                    <div class="flex justify-end pt-1">
                        <button type="submit" class="btn-primary text-xs !py-1.5 !px-4">Add Point Rule</button>
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
                                <td class="p-3.5 text-right">
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

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    pointRules: { type: Array, default: () => [] },
    rankPoints: { type: Array, default: () => [] },
    groupRankPoints: { type: Array, default: () => [] },
    gradeConfigs: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`);
const isSports = computed(() => props.event?.event_type === 'sports');

// Same "this event's actual grade set" derivation as Grade Master's datalist, just
// rendered as a <select> here since point rules reference an existing grade rather than
// defining a new one. Falls back to the legacy four when nothing's been customized.
const gradeOptions = computed(() => {
    const used = [...new Set((props.gradeConfigs ?? []).map((g) => String(g.grade || '').replace('_plus', '+')).filter(Boolean))];
    return used.length ? used : ['A+', 'A', 'B', 'C'];
});

const pointForm = useForm({ grade: '', position: null, points: null, is_group: false });

function addPointRule() {
    pointForm.post(`${base.value}/point-rules`, { preserveScroll: true, onSuccess: () => pointForm.reset() });
}

function removePointRule(id) {
    router.delete(`${base.value}/point-rules/${id}`, { preserveScroll: true });
}

const rankRows = ref([]);
const groupRankRows = ref([]);
const savingRanks = ref(false);
const savingGroupRanks = ref(false);
const seeding = ref(false);

function rankLabel(r) {
    if (r === 1) return '1st Place';
    if (r === 2) return '2nd Place';
    if (r === 3) return '3rd Place';
    return `${r}th Place`;
}

function initRows() {
    const list = (props.rankPoints ?? []).map((r, idx) => ({ _key: idx, rank: r.rank ?? idx + 1, points: r.points }));
    if (!list.length) {
        list.push({ _key: 1, rank: 1, points: 5 }, { _key: 2, rank: 2, points: 3 }, { _key: 3, rank: 3, points: 1 });
    }
    rankRows.value = list;

    groupRankRows.value = (props.groupRankPoints ?? []).map((r, idx) => ({ _key: idx, rank: r.rank ?? idx + 1, points: r.points }));
}

watch(() => props.rankPoints, initRows, { immediate: true });
watch(() => props.groupRankPoints, () => {
    groupRankRows.value = (props.groupRankPoints ?? []).map((r, idx) => ({ _key: idx, rank: r.rank ?? idx + 1, points: r.points }));
});

function addRankRow() {
    const nextRank = rankRows.value.length + 1;
    rankRows.value.push({ _key: Date.now(), rank: nextRank, points: 0 });
}

function removeRankRow(index) {
    rankRows.value.splice(index, 1);
    rankRows.value.forEach((r, i) => { r.rank = i + 1; });
}

function addGroupRankRow() {
    const nextRank = groupRankRows.value.length + 1;
    groupRankRows.value.push({ _key: Date.now(), rank: nextRank, points: 0 });
}

// Reads from the SAME rankRows/groupRankRows the table inputs mutate above — a prior
// version of this form (Settings' Points tab + the shared settings composable) kept two
// separate refs of the same name, one bound to the inputs and one read on save, so an
// edited value could be silently discarded. Only one set of refs exists here.
function saveRankPoints(isGroup = false) {
    const rows = isGroup ? groupRankRows.value : rankRows.value;
    const loading = isGroup ? savingGroupRanks : savingRanks;
    loading.value = true;
    router.put(`${base.value}/rank-points`, {
        ranks: rows.map((row) => ({ rank: row.rank, points: row.points, is_group: isGroup })),
        is_group: isGroup,
    }, {
        preserveScroll: true,
        onFinish: () => { loading.value = false; },
    });
}

function seedAthletics() {
    seeding.value = true;
    router.post(`${base.value}/rank-points/seed-athletics`, {}, {
        preserveScroll: true,
        onFinish: () => { seeding.value = false; },
    });
}
</script>
