<template>
    <SahodayaEventsLayout :title="`${event.title} — Age group matrix`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Age group matrix`" eyebrow="Reports"
                    description="Registration counts by school and age group.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export spreadsheet ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="age-group-matrix" />

        <form @submit.prevent="applyFilter" class="card !p-4 mb-4 flex flex-wrap gap-3 items-end">
            <FormField label="Filter by school" class-extra="mb-0">
                <select v-model="schoolFilter" class="field text-sm w-56">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="card overflow-x-auto p-0">
            <table class="data-table min-w-max">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>School</th>
                        <th v-for="ag in matrix.age_groups" :key="ag.key">{{ ag.label }}</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(school, idx) in matrix.schools" :key="school.id">
                        <td>{{ idx + 1 }}</td>
                        <td class="font-medium whitespace-nowrap">{{ (school.name || '').toUpperCase() }}</td>
                        <td v-for="ag in matrix.age_groups" :key="ag.key">
                            {{ matrix.matrix[school.id]?.[ag.key] ?? 0 }}
                        </td>
                        <td class="font-semibold">{{ rowTotal(school.id) }}</td>
                    </tr>
                    <tr v-if="matrix.schools?.length" class="bg-slate-50 font-semibold">
                        <td></td>
                        <td>Total</td>
                        <td v-for="ag in matrix.age_groups" :key="ag.key">{{ matrix.totals?.[ag.key] ?? 0 }}</td>
                        <td>{{ grandTotal }}</td>
                    </tr>
                    <tr v-if="!matrix.schools?.length">
                        <td :colspan="(matrix.age_groups?.length ?? 0) + 3" class="p-6 text-center text-slate-400">
                            No registrations with age groups yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    matrix: Object,
    schools: Array,
    filterSchoolId: [String, Number],
    xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const schoolFilter = ref(props.filterSchoolId ?? '');

function rowTotal(schoolId) {
    const row = props.matrix?.matrix?.[schoolId] ?? {};
    return Object.values(row).reduce((n, v) => n + Number(v || 0), 0);
}

const grandTotal = computed(() =>
    (props.matrix?.schools ?? []).reduce((n, s) => n + rowTotal(s.id), 0),
);

function applyFilter() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/age-group-matrix`,
        { school_id: schoolFilter.value || undefined },
        { preserveState: true },
    );
}
</script>
