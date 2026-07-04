<template>
    <SahodayaAdminLayout title="Sports results" :sahodaya="sahodaya">
        <PageHeader
            title="Sports results"
            eyebrow="Sports Meet"
            description="Published results across sports events in your cluster."
        />

        <section class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table v-if="results.length" class="data-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Item</th>
                            <th>Athlete</th>
                            <th>School</th>
                            <th>Rank</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in results" :key="i">
                            <td>{{ row.event_title }}</td>
                            <td class="font-medium">{{ row.item_title }}</td>
                            <td>
                                <span class="font-medium">{{ row.student_name }}</span>
                                <span v-if="row.reg_no" class="block text-xs text-slate-500 font-mono">{{ row.reg_no }}</span>
                            </td>
                            <td>{{ row.school_name }}</td>
                            <td>{{ row.position }}</td>
                            <td class="font-mono text-sm">{{ row.measurement || row.score || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!results.length" class="p-6 text-sm text-slate-500">No published sports results yet.</p>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    events: { type: Array, default: () => [] },
    results: { type: Array, default: () => [] },
});
</script>
