<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <!-- Participation: the State competes Sahodaya against Sahodaya, so that is the headline
             figure, with schools shown as the origin of the participants rather than as competitors. -->
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-1 text-sm font-bold text-slate-900">Participation</h2>
            <p class="mb-4 text-xs text-slate-500">
                Counted from certified qualifier snapshots — the State's own record, not a live read
                of each Sahodaya's database.
            </p>
            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Stat label="Sahodayas" :value="m.participation.sahodayas" emphasis />
                <Stat label="On the platform" :value="m.participation.managed" />
                <Stat label="From outside" :value="m.participation.from_outside" />
                <Stat label="Schools represented" :value="m.participation.schools" />
                <Stat label="Registrations" :value="m.participation.registrations" />
                <Stat label="Items entered" :value="m.participation.items_entered" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-1 text-sm font-bold text-slate-900">Qualifier intake and scrutiny</h2>
            <p class="mb-4 text-xs text-slate-500">Submissions from Sahodayas, and where they have reached.</p>
            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <Stat label="Submissions" :value="m.intake.submissions" emphasis />
                <Stat label="Awaiting scrutiny" :value="m.intake.awaiting_scrutiny" :tone="m.intake.awaiting_scrutiny ? 'warn' : null" />
                <Stat label="Approved" :value="m.intake.approved_intakes" />
                <Stat label="Rejected" :value="m.intake.rejected_intakes" />
                <Stat label="Entries" :value="m.intake.entries" />
                <Stat label="Entries pending" :value="m.intake.entries_pending" :tone="m.intake.entries_pending ? 'warn' : null" />
                <Stat label="Entries approved" :value="m.intake.entries_approved" tone="ok" />
                <Stat label="Entries rejected" :value="m.intake.entries_rejected" />
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-1 text-sm font-bold text-slate-900">Conduct</h2>
            <p class="mb-4 text-xs text-slate-500">Progress through the event itself.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Attendance marked" :value="m.conduct.attendance_marked" />
                <Stat label="Attendance pending" :value="m.conduct.attendance_pending" :tone="m.conduct.attendance_pending ? 'warn' : null" />
                <Stat label="Marks entered" :value="m.conduct.marks_entered" />
                <Stat label="Marks pending" :value="m.conduct.marks_pending" :tone="m.conduct.marks_pending ? 'warn' : null" />
            </div>
            <p class="mt-4 text-xs text-slate-400">
                Scheduling and certificate progress appear here once those parts of the module are
                built — they are left out rather than shown as a zero that would read as "nothing to do".
            </p>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed } from 'vue';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';
import Stat from '@/Components/state/fest/StateStat.vue';

const props = defineProps({
    event: Object,
    events: Array,
    sahodayas: Array,
    permissions: Array,
    metrics: Object,
});

const m = computed(() => props.metrics);
</script>
