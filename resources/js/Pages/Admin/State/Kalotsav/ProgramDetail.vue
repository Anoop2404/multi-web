<template>
    <AdminLayout :title="program.title">
        <PageHeader :title="program.title" eyebrow="State Kalotsav"
                    :description="`Academic year ${program.academic_year || '—'} · ${program.status}`">
            <template #actions>
                <Link :href="`/admin/kalotsav/${program.id}/results`" class="btn-secondary text-sm">Results</Link>
                <Link :href="`/admin/kalotsav/${program.id}/winners`" class="btn-primary text-sm">Winners</Link>
            </template>
        </PageHeader>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="card card--flush overflow-hidden">
                <div class="p-4 border-b border-slate-100 bg-slate-50/80"><h3 class="section-title !mb-0">Cluster propagation</h3></div>
                <table class="data-table">
                    <thead><tr><th>Sahodaya</th><th>Level</th><th>Event</th></tr></thead>
                    <tbody>
                        <tr v-for="prop in program.propagations ?? []" :key="prop.id">
                            <td>{{ prop.sahodaya?.name || '—' }}</td>
                            <td class="text-xs capitalize">{{ prop.level_round }}</td>
                            <td class="text-xs">{{ prop.tenant_event_id ? 'Linked' : 'Pending' }}</td>
                        </tr>
                        <tr v-if="!(program.propagations ?? []).length"><td colspan="3" class="p-6 text-center text-slate-400">Not propagated yet.</td></tr>
                    </tbody>
                </table>
            </section>

            <section class="card card--flush overflow-hidden">
                <div class="p-4 border-b border-slate-100 bg-slate-50/80"><h3 class="section-title !mb-0">Program items</h3></div>
                <ul class="divide-y text-sm">
                    <li v-for="item in program.items ?? []" :key="item.id" class="px-4 py-3">{{ item.title || item.name }}</li>
                    <li v-if="!(program.items ?? []).length" class="p-6 text-center text-slate-400">No items defined.</li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ program: Object });
</script>
