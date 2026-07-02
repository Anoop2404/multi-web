<template>
    <AdminLayout :title="`${program.title} — Winners`">
        <PageHeader :title="`${program.title} — Qualified winners`" eyebrow="State Kalotsav"
                    description="Participants promoted to the next level across clusters.">
            <template #actions>
                <a :href="`/admin/kalotsav/${program.id}/winners/export`" class="btn-secondary text-sm">Export CSV</a>
                <Link :href="`/admin/kalotsav/${program.id}`" class="btn-primary text-sm">← Program</Link>
            </template>
        </PageHeader>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>Participant</th><th>Reg No</th><th>Item</th><th>From event</th><th>Next level</th><th>Promoted</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(w, i) in winners" :key="i">
                        <td class="font-medium">{{ w.participant || '—' }}</td>
                        <td class="font-mono text-xs">{{ w.reg_no || '—' }}</td>
                        <td>{{ w.item || '—' }}</td>
                        <td class="text-xs">{{ w.from_event || '—' }}</td>
                        <td class="text-xs capitalize">{{ w.next_level || '—' }}</td>
                        <td class="text-xs">{{ w.promoted_at || '—' }}</td>
                    </tr>
                    <tr v-if="!winners.length"><td colspan="6" class="p-8 text-center text-slate-400">No qualified winners yet.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ program: Object, winners: { type: Array, default: () => [] } });
</script>
