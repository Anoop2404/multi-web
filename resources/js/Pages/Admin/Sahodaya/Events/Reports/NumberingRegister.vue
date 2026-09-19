<template>
    <SahodayaEventsLayout :title="`${event.title} — Numbering register`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Numbering register`" eyebrow="Reports"
                    description="Fest ID, item registration number and chest number for all active registrations.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="numbering-register" />

        <form @submit.prevent="applyFilter" class="card !p-4 mb-4 flex flex-wrap gap-3 items-end">
            <FormField label="Filter by school" class-extra="mb-0">
                <SearchableSelect v-model="schoolFilter" :options="schools" :all-option="true" all-label="All schools" class="w-64" />
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table text-sm">
                    <thead>
                        <tr>
                            <th>Head</th><th>Item</th><th>Category</th><th>Type</th><th>Gender</th><th>School</th>
                            <th>Participant</th><th>Reg no</th><th>Status</th><th>Fest ID</th><th>Item reg</th><th>Chest</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.participant_id">
                            <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                            <td>{{ row.item }}</td>
                            <td class="text-xs text-slate-500">{{ row.category_label ?? '—' }}</td>
                            <td class="text-xs text-slate-500">{{ row.type_label ?? '—' }}</td>
                            <td class="text-xs text-slate-500">{{ row.gender_label ?? '—' }}</td>
                            <td class="text-xs">{{ row.school }}</td>
                            <td class="font-medium">{{ row.name }}</td>
                            <td>{{ row.reg_no ?? '—' }}</td>
                            <td><span :class="row.reg_status === 'approved' ? 'text-emerald-700' : 'text-amber-700'">{{ row.reg_status }}</span></td>
                            <td class="font-mono text-xs">{{ row.fest_id ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ row.item_reg ?? '—' }}</td>
                            <td class="font-mono font-bold">{{ row.chest_no ?? '—' }}</td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="12" class="p-6 text-center text-slate-400">No registrations yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object,
    rows: Array, xlsUrl: String, schools: { type: Array, default: () => [] },
    filterSchoolId: [String, Number],
    activityLogs: { type: Array, default: () => [] },
});

const schoolFilter = ref(props.filterSchoolId ?? '');

function applyFilter() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/numbering-register`,
        { school_id: schoolFilter.value || undefined },
        { preserveState: true },
    );
}
</script>
