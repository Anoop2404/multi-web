<template>
    <AdminLayout title="MCQ results">
        <PageHeader title="Cross-cluster MCQ results" eyebrow="State admin"
                    description="Published Talent Search / MCQ exam results aggregated from Sahodaya clusters." />

        <form @submit.prevent="applyFilters" class="card flex flex-wrap gap-3 items-end mb-6">
            <div>
                <label class="form-label">Cluster</label>
                <select v-model="form.cluster" class="field max-w-xs">
                    <option value="">All clusters</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filter</button>
        </form>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>Cluster</th><th>Exam</th><th>Hall ticket</th><th>Participant</th><th>School</th><th>Score</th><th>%</th><th>Grade</th><th>Rank</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in results" :key="i">
                        <td class="text-xs">{{ r.cluster }}</td>
                        <td class="text-xs">{{ r.exam }}</td>
                        <td class="font-mono text-xs">{{ r.hall_ticket || '—' }}</td>
                        <td>{{ r.participant }}</td>
                        <td class="text-xs">{{ r.school }}</td>
                        <td class="font-mono">{{ r.score ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ r.percentage != null ? `${r.percentage}%` : '—' }}</td>
                        <td>{{ r.grade || '—' }}</td>
                        <td class="font-semibold">{{ r.rank }}</td>
                    </tr>
                    <tr v-if="!results.length"><td colspan="9" class="p-8 text-center text-slate-400">No published results match filters.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    sahodayas: { type: Array, default: () => [] },
    results: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    cluster: props.filters.cluster ?? '',
});

function applyFilters() {
    const q = {};
    if (form.cluster) q.cluster = form.cluster;
    router.get('/admin/mcq-results', q, { preserveState: true });
}
</script>
