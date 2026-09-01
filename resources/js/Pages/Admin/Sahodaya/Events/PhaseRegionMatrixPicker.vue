<template>
    <SahodayaAdminLayout title="Region Matrix" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">
            <PageHeader title="Region Matrix" eyebrow="Fest · Regional phases"
                        description="Pick an event to view and manage which region each school competes in, per regional phase." />

            <div v-if="events.length === 0" class="card text-sm text-slate-400">
                No events use the phased regional workflow yet. A region matrix only applies to events with at least one regional phase (Phases &rarr; mark a phase Regional, then choose its allowed regions).
            </div>
            <ul v-else class="card divide-y divide-slate-100 !p-0">
                <li v-for="event in events" :key="event.id">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/phases/regions-matrix`"
                          class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 transition">
                        <div>
                            <p class="font-semibold text-slate-800">{{ event.title }}</p>
                            <p class="text-xs text-slate-400">{{ event.event_start ? new Date(event.event_start).toLocaleDateString() : '—' }}</p>
                        </div>
                        <span class="text-xs font-semibold text-[#0f3d7a]">Open matrix &rarr;</span>
                    </Link>
                </li>
            </ul>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    approvedSchoolsCount: Number,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    events: { type: Array, default: () => [] },
});
</script>
