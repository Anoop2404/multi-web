<template>
    <SahodayaEventsLayout title="Athletic records" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :program-events="programEvents" :show-header-title="false">
        <PageHeader
            title="Athletic records"
            eyebrow="Sports Meet"
            description="Current records across all sports events in your cluster."
        />

        <section class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">Record register</h3>
            </div>
            <table v-if="records.length" class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Item</th>
                        <th>Group</th>
                        <th>Record</th>
                        <th>Holder</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in records" :key="row.id">
                        <td class="text-sm">{{ row.event?.title }}</td>
                        <td class="font-medium">{{ row.item?.title }}</td>
                        <td class="text-xs uppercase">{{ row.class_group }} / {{ row.gender }}</td>
                        <td class="font-mono">{{ row.record_value }} {{ row.record_unit }}</td>
                        <td>{{ row.holder_name || '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-6 text-sm text-slate-500">No athletic records configured yet.</p>
        </section>

        <section class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">Recent record breaks</h3>
            </div>
            <table v-if="recentBreaks.length" class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Item</th>
                        <th>Athlete</th>
                        <th>Value</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="br in recentBreaks" :key="br.id">
                        <td>{{ br.event?.title }}</td>
                        <td>{{ br.item?.title }}</td>
                        <td>{{ br.participant?.student?.name || '—' }}</td>
                        <td class="font-mono">{{ br.new_value }}</td>
                        <td class="text-xs text-slate-500">{{ br.broken_at }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-6 text-sm text-slate-500">No record breaks logged this season.</p>
        </section>
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    programEvents: { type: Array, default: () => [] },
    records: { type: Array, default: () => [] },
    recentBreaks: { type: Array, default: () => [] },
});
</script>
