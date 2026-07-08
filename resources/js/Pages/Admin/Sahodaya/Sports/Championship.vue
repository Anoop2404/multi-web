<template>
    <SahodayaEventsLayout title="House championship" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :program-events="programEvents" :show-header-title="false">
        <PageHeader
            title="House championship"
            eyebrow="Sports Meet"
            description="Cross-event house standings — open an event for detailed points."
        />

        <section v-if="houseStandings?.length" class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100 bg-slate-50/80">
                <h3 class="section-title !mb-0">Cross-event house standings</h3>
            </div>
            <table class="data-table">
                <thead><tr><th>#</th><th>House</th><th>Total points</th><th>Events</th></tr></thead>
                <tbody>
                    <tr v-for="(h, i) in houseStandings" :key="h.house_id">
                        <td class="font-semibold">{{ i + 1 }}</td>
                        <td>
                            <span v-if="h.color" class="inline-block w-2 h-2 rounded-full mr-2" :style="{ background: h.color }"></span>
                            {{ h.house_name }}
                        </td>
                        <td class="font-bold text-indigo-700">{{ h.total_points }}</td>
                        <td class="text-xs">{{ h.events_count ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card card--flush overflow-hidden">
            <table v-if="events.length" class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Results</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ev in events" :key="ev.id">
                        <td class="font-medium">{{ ev.title }}</td>
                        <td class="text-xs capitalize">{{ ev.status?.replace('_', ' ') }}</td>
                        <td>
                            <span v-if="ev.results_published" class="badge badge--success">Published</span>
                            <span v-else class="badge badge--muted">Pending</span>
                        </td>
                        <td class="text-right">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${ev.id}/championship`" class="link-brand text-xs">
                                View standings →
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-6 text-sm text-slate-500">No sports events yet. Create one from the Sports Meet dashboard.</p>
        </section>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    events: { type: Array, default: () => [] },
    programEvents: { type: Array, default: () => [] },
    houseStandings: { type: Array, default: () => [] },
});
</script>
