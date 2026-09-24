<template>
    <SahodayaEventsLayout :title="`${event.title} — School Team Managers`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — School Team Managers`" eyebrow="Reports"
                    description="Team manager and contingent official contact details on file for each participating school, with a fallback to the Events Coordinator when no dedicated manager has been entered.">
            <template #actions>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
                <a v-if="registrationSheetUrl" :href="registrationSheetUrl" target="_blank" rel="noopener"
                   class="btn-secondary text-sm flex items-center gap-1.5 shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Registration Sheet (Sign-In)
                </a>
            </template>
        </PageHeader>

        <!-- School Search / Filter -->
        <div class="card mb-4 !py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="w-full sm:w-80">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search school by name or code…"
                    class="field text-sm w-full"
                />
            </div>
            <div class="text-xs text-slate-500">
                Showing {{ filteredRows.length }} of {{ rows.length }} schools
            </div>
        </div>

        <!-- Data Table -->
        <div class="card overflow-hidden p-0 shadow-sm border border-slate-200">
            <div class="overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs">
                            <th class="w-12 text-center">#</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">School</th>
                            <th class="text-center py-3 px-3 font-semibold text-indigo-900 bg-indigo-50/50 whitespace-nowrap">
                                Students
                            </th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Team Manager 1</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Team Manager 2</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="(row, idx) in filteredRows" :key="row.school_id" class="hover:bg-slate-50/80 transition align-top">
                            <td class="text-center text-xs text-slate-400 py-3">{{ idx + 1 }}</td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-900 uppercase">{{ row.school_name }}</div>
                                <div v-if="row.school_prefix" class="text-xs text-slate-400 font-mono">{{ row.school_prefix }}</div>
                            </td>
                            <td class="text-center py-3 px-3 font-bold text-indigo-700 bg-indigo-50/30">
                                {{ row.unique_student_count }}
                            </td>
                            <td class="py-3 px-4">
                                <ManagerCell :name="row.manager_name_1" :phone="row.manager_phone_1"
                                             :email="row.manager_email_1" :role="row.manager_role_1" />
                            </td>
                            <td class="py-3 px-4">
                                <ManagerCell :name="row.manager_name_2" :phone="row.manager_phone_2"
                                             :email="row.manager_email_2" :role="row.manager_role_2" />
                            </td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td colspan="5" class="p-8 text-center text-slate-400">
                                {{ search ? 'No schools match your search query.' : 'No participating schools found for this event yet.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref, h } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    pdfUrl: String,
    xlsUrl: String,
    registrationSheetUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const search = ref('');

const filteredRows = computed(() => {
    if (!search.value.trim()) {
        return props.rows;
    }
    const q = search.value.toLowerCase();
    return props.rows.filter(r =>
        (r.school_name && r.school_name.toLowerCase().includes(q)) ||
        (r.school_prefix && r.school_prefix.toLowerCase().includes(q))
    );
});

const ManagerCell = {
    props: { name: String, phone: String, email: String, role: String },
    setup(cellProps) {
        return () => {
            if (!cellProps.name && !cellProps.phone) {
                return null;
            }
            return h('div', [
                h('div', { class: 'font-semibold text-base text-slate-900' }, cellProps.name || null),
                cellProps.phone ? h('div', { class: 'text-xs text-slate-500 mt-0.5' }, cellProps.phone) : null,
                cellProps.role ? h('div', { class: 'text-xs text-amber-700 mt-0.5' }, cellProps.role) : null,
            ]);
        };
    },
};
</script>
