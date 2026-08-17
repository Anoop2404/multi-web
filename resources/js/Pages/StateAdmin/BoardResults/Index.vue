<template>
    <AdminLayout title="Board results — state">
        <div class="max-w-5xl mx-auto space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Board results across Sahodayas</h1>
                    <p class="text-sm text-slate-500 mt-1">Light consolidated view of published / pending CBSE board results.</p>
                </div>
                <form class="flex gap-2" @submit.prevent="apply">
                    <input v-model="year" class="field w-36" placeholder="2024-25">
                    <button type="submit" class="btn-primary text-sm">Apply</button>
                </form>
            </div>

            <div class="grid sm:grid-cols-4 gap-3">
                <div class="card !p-4">
                    <p class="text-xs uppercase text-slate-500">Sahodayas</p>
                    <p class="text-2xl font-semibold">{{ totals.sahodayas }}</p>
                </div>
                <div class="card !p-4">
                    <p class="text-xs uppercase text-slate-500">Published</p>
                    <p class="text-2xl font-semibold">{{ totals.published }}</p>
                </div>
                <div class="card !p-4">
                    <p class="text-xs uppercase text-slate-500">Pending</p>
                    <p class="text-2xl font-semibold">{{ totals.pending }}</p>
                </div>
                <div class="card !p-4">
                    <p class="text-xs uppercase text-slate-500">Avg pass %</p>
                    <p class="text-2xl font-semibold">{{ totals.avg_pass_percent ?? '—' }}</p>
                </div>
            </div>

            <div class="card !p-0 overflow-x-auto">
                <table class="data-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Sahodaya</th>
                            <th>Published</th>
                            <th>Pending</th>
                            <th>Schools</th>
                            <th>Avg pass %</th>
                            <th>X / XII</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in clusters" :key="c.sahodaya_id">
                            <td class="font-medium">{{ c.sahodaya_name }}</td>
                            <td>{{ c.published }}</td>
                            <td>{{ c.pending }}</td>
                            <td>{{ c.schools_reported }}</td>
                            <td>{{ c.avg_pass_percent ?? '—' }}</td>
                            <td>{{ c.by_class?.[10] ?? 0 }} / {{ c.by_class?.[12] ?? 0 }}</td>
                        </tr>
                        <tr v-if="!clusters.length">
                            <td colspan="6" class="p-8 text-center text-slate-400">No Sahodaya clusters found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({}) },
    clusters: { type: Array, default: () => [] },
});

const year = ref(props.filters.academic_year || '');

function apply() {
    router.get('/admin/board-results', { academic_year: year.value }, { preserveState: true });
}
</script>
