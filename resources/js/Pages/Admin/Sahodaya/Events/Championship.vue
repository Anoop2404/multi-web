<template>
    <SahodayaEventsLayout :title="`${event.title} — Individual Championship`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Individual Championship`" eyebrow="Scoring"
                    description="Individual championship points leaderboard." />
        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'operations'" />

        <!-- Stats cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6 mt-4">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-slate-800">{{ leaderboard.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Ranked students</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-indigo-700">{{ stats.top_points }} pts</p>
                <p class="text-xs text-slate-500 mt-1">Highest points</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.total_points }} pts</p>
                <p class="text-xs text-slate-500 mt-1">Total distributed</p>
            </div>
        </div>

        <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
            <p class="text-sm text-gray-600">IC points leaderboard from published marks.</p>
            <button @click="recalculate" class="btn-primary text-xs">Recalculate from marks</button>
        </div>

        <!-- Filters panel -->
        <div class="card mb-4 space-y-3">
            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by category</label>
                    <SearchableSelect v-model="filterCategory" class="mt-1 w-44" :options="categoryOptionsFromLeaderboard"
                                      :all-option="true" all-label="All categories" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by gender</label>
                    <SearchableSelect v-model="filterGender" class="mt-1 w-36"
                                      :options="[{ value: 'male', label: 'Boys' }, { value: 'female', label: 'Girls' }]"
                                      :all-option="true" all-label="All genders" />
                </div>
            </div>
        </div>

        <!-- Category merge rules -->
        <div class="card mb-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="section-title !mb-0">Category merge rules</h3>
                    <p class="section-desc mt-0.5">
                        Tally two or more categories together as one bucket instead of scoring them separately — e.g. merge "Category 3" into "Open".
                        Applies to this school/team overall scoreboard for any target category; the individual leaderboard above only honors a merge whose target is LP, UP, HS, HSS, or Open.
                    </p>
                </div>
                <button type="button" class="btn-secondary text-xs whitespace-nowrap" @click="addingMergeRule = !addingMergeRule">
                    {{ addingMergeRule ? 'Cancel' : '+ Add merge rule' }}
                </button>
            </div>

            <div v-if="mergeGroups.length" class="space-y-2">
                <div v-for="(group, idx) in mergeGroups" :key="idx"
                     class="flex flex-wrap items-center gap-2 text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                    <span v-for="src in group.sources" :key="src" class="px-2 py-0.5 rounded-full bg-white border border-slate-200 text-xs font-medium text-slate-700">
                        {{ labelFor(src) }}
                    </span>
                    <span class="text-slate-400">→</span>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-xs font-bold text-indigo-700">{{ labelFor(group.target) }}</span>
                    <button type="button" class="ml-auto text-xs font-semibold text-rose-600" @click="removeMergeGroup(idx)">Remove</button>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400">No merge rules — every category scores on its own.</p>

            <div v-if="addingMergeRule" class="border-t border-slate-200 pt-3 space-y-3">
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-1.5">Categories to merge (pick 2 or more)</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="opt in categoryOptions" :key="opt.value"
                               class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full border cursor-pointer"
                               :class="newGroupSources.includes(opt.value) ? 'bg-indigo-50 border-indigo-300 text-indigo-800' : 'bg-white border-slate-200 text-slate-600'">
                            <input type="checkbox" class="rounded" :value="opt.value" v-model="newGroupSources">
                            {{ opt.label }}
                        </label>
                    </div>
                </div>
                <div v-if="newGroupSources.length >= 2">
                    <p class="text-xs font-semibold text-gray-600 mb-1.5">Merge into which one?</p>
                    <SearchableSelect v-model="newGroupTarget" class="w-56"
                                      :options="categoryOptions.filter(o => newGroupSources.includes(o.value))"
                                      :all-option="false" placeholder="Choose target category" />
                </div>
                <button type="button" class="btn-primary text-xs" :disabled="newGroupSources.length < 2 || !newGroupTarget" @click="saveMergeGroup">
                    Save merge rule
                </button>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b"><tr>
                        <th class="p-3 text-left">Category rank</th><th class="p-3 text-left">Student</th>
                        <th class="p-3 text-left">School</th><th class="p-3 text-left">Category</th>
                        <th class="p-3 text-right">Points</th><th class="p-3 text-right">Overall rank</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="row in filteredLeaderboard" :key="row.student.id" class="border-t">
                            <td class="p-3 font-bold">#{{ row.rank }}</td>
                            <td class="p-3">
                                <span class="font-medium text-slate-800">{{ row.student.name }}</span>
                                <span class="text-xs text-gray-400 font-mono ml-2">{{ row.student.reg_no }}</span>
                            </td>
                            <td class="p-3 text-slate-600">{{ row.school }}</td>
                            <td class="p-3 uppercase text-xs text-indigo-700 font-medium">{{ row.category }} · {{ row.gender }}</td>
                            <td class="p-3 text-right font-mono font-semibold text-slate-900">{{ row.points }}</td>
                            <td class="p-3 text-right font-mono text-xs text-slate-400">#{{ row.overall_rank }}</td>
                        </tr>
                        <tr v-if="!filteredLeaderboard.length"><td colspan="6" class="p-8 text-gray-400 text-center">No matching leaderboard entries</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, leaderboard: Array,
    activityLogs: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    categoryMergeGroups: { type: Array, default: () => [] },
});

const filterCategory = ref('');
const filterGender = ref('');

const categories = computed(() => {
    const set = new Set();
    for (const row of props.leaderboard ?? []) {
        if (row.category) set.add(row.category.toLowerCase());
    }
    return [...set];
});

const categoryOptionsFromLeaderboard = computed(() => categories.value.map(cat => ({ value: cat, label: cat.toUpperCase() })));

const mergeGroups = ref(props.categoryMergeGroups.map(g => ({ ...g })));
const addingMergeRule = ref(false);
const newGroupSources = ref([]);
const newGroupTarget = ref(null);

function labelFor(key) {
    return props.categoryOptions.find(o => o.value === key)?.label ?? key;
}

function removeMergeGroup(idx) {
    mergeGroups.value.splice(idx, 1);
    saveMergeGroups();
}

function saveMergeGroup() {
    mergeGroups.value.push({ target: newGroupTarget.value, sources: [...newGroupSources.value] });
    newGroupSources.value = [];
    newGroupTarget.value = null;
    addingMergeRule.value = false;
    saveMergeGroups();
}

function saveMergeGroups() {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/category-merge`, {
        groups: mergeGroups.value,
    }, { preserveScroll: true });
}

const stats = computed(() => {
    const points = (props.leaderboard ?? []).map(r => Number(r.points || 0));
    return {
        top_points: points.length ? Math.max(...points) : 0,
        total_points: points.reduce((a, b) => a + b, 0),
    };
});

const filteredLeaderboard = computed(() => {
    let list = props.leaderboard ?? [];
    if (filterCategory.value) {
        list = list.filter(r => String(r.category).toLowerCase() === filterCategory.value.toLowerCase());
    }
    if (filterGender.value) {
        list = list.filter(r => String(r.gender).toLowerCase() === filterGender.value.toLowerCase());
    }
    return list;
});

function recalculate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/recalculate`, {}, { preserveScroll: true });
}
</script>
