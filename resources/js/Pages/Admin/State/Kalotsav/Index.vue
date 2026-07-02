<template>
    <AdminLayout title="State Kalotsav">
        <PageHeader title="State Kalotsav" eyebrow="State admin"
                    description="Cross-cluster Kalotsav programs propagated from state to Sahodaya complexes." />

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>Program</th><th>Year</th><th>Status</th><th>Clusters</th><th>Items</th><th></th></tr>
                </thead>
                <tbody>
                    <tr v-for="p in programs" :key="p.id">
                        <td class="font-medium">{{ p.title }}</td>
                        <td class="text-xs">{{ p.academic_year || '—' }}</td>
                        <td class="text-xs capitalize">{{ p.status }}</td>
                        <td>{{ p.propagations_count ?? 0 }}</td>
                        <td>{{ p.items_count ?? 0 }}</td>
                        <td class="text-right space-x-2 whitespace-nowrap">
                            <Link :href="`/admin/kalotsav/${p.id}`" class="link-brand text-xs">View</Link>
                            <Link :href="`/admin/kalotsav/${p.id}/results`" class="text-xs text-indigo-700 font-semibold">Results</Link>
                            <Link :href="`/admin/kalotsav/${p.id}/winners`" class="text-xs text-emerald-700 font-semibold">Winners</Link>
                        </td>
                    </tr>
                    <tr v-if="!programs.length"><td colspan="6" class="p-8 text-center text-slate-400">No Kalotsav state programs yet.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ programs: { type: Array, default: () => [] } });
</script>
