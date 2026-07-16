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
                    <select v-model="filterCategory" class="field text-sm mt-1 w-44">
                        <option value="">All categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">
                            {{ cat.toUpperCase() }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by gender</label>
                    <select v-model="filterGender" class="field text-sm mt-1 w-36">
                        <option value="">All genders</option>
                        <option value="male">Boys</option>
                        <option value="female">Girls</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b"><tr>
                    <th class="p-3 text-left">Rank</th><th class="p-3 text-left">Student</th>
                    <th class="p-3 text-left">School</th><th class="p-3 text-left">Category</th><th class="p-3 text-right">Points</th>
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
                    </tr>
                    <tr v-if="!filteredLeaderboard.length"><td colspan="5" class="p-8 text-gray-400 text-center">No matching leaderboard entries</td></tr>
                </tbody>
            </table>
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

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, leaderboard: Array,
    activityLogs: { type: Array, default: () => [] },
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
