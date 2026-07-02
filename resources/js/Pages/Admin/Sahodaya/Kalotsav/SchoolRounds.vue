<template>
    <SahodayaAdminLayout title="School rounds" :sahodaya="sahodaya">
        <PageHeader title="Kalotsav school rounds" eyebrow="Kalotsav"
                    description="School-created kalotsav events in your cluster — link to Sahodaya parent events and promote winners." />

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>School</th><th>Event</th><th>Items</th><th>Registrations</th><th>Results</th><th>Linked</th><th></th></tr>
                </thead>
                <tbody>
                    <tr v-for="ev in schoolEvents" :key="ev.id">
                        <td>{{ ev.school }}</td>
                        <td class="font-medium">{{ ev.title }}</td>
                        <td>{{ ev.items_count }}</td>
                        <td>{{ ev.registrations_count }}</td>
                        <td><span :class="ev.results_published ? 'text-green-700' : 'text-slate-400'">{{ ev.results_published ? 'Yes' : 'No' }}</span></td>
                        <td><span :class="ev.linked ? 'text-green-700' : 'text-amber-700'">{{ ev.linked ? 'Yes' : 'No' }}</span></td>
                        <td class="text-right"><Link :href="`/sahodaya-admin/${sahodaya.id}/events/${ev.id}`" class="link-brand text-xs">View →</Link></td>
                    </tr>
                    <tr v-if="!schoolEvents.length"><td colspan="7" class="p-8 text-center text-slate-400">No school rounds yet.</td></tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
defineProps({ sahodaya: Object, schoolEvents: { type: Array, default: () => [] }, parentEvents: { type: Array, default: () => [] } });
</script>
