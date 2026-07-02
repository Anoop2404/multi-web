<template>
    <AdminLayout :title="`${program.title} — Results`">
        <PageHeader :title="`${program.title} — Cluster results`" eyebrow="State Kalotsav"
                    description="Results publication status across Sahodaya clusters.">
            <template #actions>
                <Link :href="`/admin/kalotsav/${program.id}`" class="btn-secondary text-sm">← Program</Link>
            </template>
        </PageHeader>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>Sahodaya</th><th>Level</th><th>Event</th><th>Results</th><th>Marks entered</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in clusterResults" :key="i">
                        <td>{{ row.sahodaya || '—' }}</td>
                        <td class="text-xs capitalize">{{ row.level }}</td>
                        <td>{{ row.event_title || (row.status === 'not_propagated' ? 'Not propagated' : '—') }}</td>
                        <td>
                            <span v-if="row.results_published" class="text-green-700 text-xs font-semibold">Published</span>
                            <span v-else class="text-slate-400 text-xs">Pending</span>
                        </td>
                        <td>{{ row.registrations_count ?? '—' }}</td>
                    </tr>
                    <tr v-if="!clusterResults.length"><td colspan="5" class="p-8 text-center text-slate-400">No cluster data.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ program: Object, clusterResults: { type: Array, default: () => [] } });
</script>
