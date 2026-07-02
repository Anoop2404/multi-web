<template>
    <AdminLayout title="Sports results">
        <PageHeader title="Cross-cluster sports results" eyebrow="State admin"
                    description="Published sports meet results aggregated from Sahodaya clusters." />

        <form @submit.prevent="applyFilters" class="card flex flex-wrap gap-3 items-end mb-6">
            <div>
                <label class="form-label">Cluster</label>
                <select v-model="form.cluster" class="field max-w-xs">
                    <option value="">All clusters</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Age group</label>
                <select v-model="form.age_group" class="field max-w-xs">
                    <option value="">All</option>
                    <option v-for="(label, key) in ageGroups" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Gender</label>
                <select v-model="form.gender" class="field max-w-xs">
                    <option value="">All</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="mixed">Mixed</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filter</button>
        </form>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>Cluster</th><th>Event</th><th>Item</th><th>Age</th><th>Gender</th><th>Pos</th><th>Result</th><th>Participant</th><th>School</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in results" :key="i">
                        <td class="text-xs">{{ r.cluster }}</td>
                        <td class="text-xs">{{ r.event }}</td>
                        <td>{{ r.item }}</td>
                        <td class="text-xs">{{ r.age_group }}</td>
                        <td class="text-xs capitalize">{{ r.gender }}</td>
                        <td class="font-semibold">{{ r.position }}</td>
                        <td class="text-xs">{{ r.measurement || '—' }}</td>
                        <td>{{ r.participant }}</td>
                        <td class="text-xs">{{ r.school }}</td>
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
    ageGroups: { type: Object, default: () => ({}) },
});

const form = reactive({
    cluster: props.filters.cluster ?? '',
    age_group: props.filters.age_group ?? '',
    gender: props.filters.gender ?? '',
});

function applyFilters() {
    const q = {};
    if (form.cluster) q.cluster = form.cluster;
    if (form.age_group) q.age_group = form.age_group;
    if (form.gender) q.gender = form.gender;
    router.get('/admin/sports', q, { preserveState: true });
}
</script>
