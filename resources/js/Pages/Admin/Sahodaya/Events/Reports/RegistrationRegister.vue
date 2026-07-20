<template>
    <SahodayaEventsLayout :title="`${event.title} — Registration Register`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Registration & Fees Register`" eyebrow="Reports"
                    :description="event.event_type === 'sports'
                        ? 'Grouped by Sport Event — Fest ID, item reg, and chest numbers per item row.'
                        : 'Grouped by item head — Fest ID, item reg, and chest numbers per item row.'">
            <template #actions>
                <a :href="exportUrl" class="btn-primary text-sm">Export CSV ↓</a>
                <Link :href="feesUrl" class="btn-secondary text-sm">Event fees →</Link>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="registration-register" />

        <div class="notice-banner notice-banner--info mb-4 text-sm max-w-3xl">
            <p class="font-semibold text-[#0f3d7a] mb-1">How IDs work</p>
            <ul class="list-disc pl-4 space-y-0.5 text-slate-700">
                <li><strong>Fest ID</strong> — one unique number per student for the whole event.</li>
                <li><strong>Item reg no.</strong> — per item registration, sequence starts from item settings.</li>
                <li><strong>Chest no</strong> — per item when approved; each item has its own chest sequence.</li>
            </ul>
        </div>

        <ReportHeadFilter v-if="hasItemHeads"
                          v-model="headFilter"
                          v-model:item-id="itemFilter"
                          :heads="headsForFilter"
                          :head-item-groups="headItemGroups"
                          :is-sports="event.event_type === 'sports'"
                          @apply="applyFilter">
            <template #extra>
                <FormField label="School" class-extra="mb-0 min-w-[12rem]">
                    <select v-model="schoolFilter" class="field text-sm">
                        <option value="">All schools</option>
                        <option v-for="(name, id) in schools" :key="id" :value="id">{{ name }}</option>
                    </select>
                </FormField>
            </template>
        </ReportHeadFilter>

        <div v-else class="card !p-4 mb-4 flex flex-wrap gap-3 items-end">
            <FormField label="School" class-extra="mb-0">
                <select v-model="schoolFilter" class="field text-sm w-56" @change="applyFilter">
                    <option value="">All schools</option>
                    <option v-for="(name, id) in schools" :key="id" :value="id">{{ name }}</option>
                </select>
            </FormField>
        </div>

        <div v-if="schoolSummaries.length && !schoolFilter" class="card card--flush mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm">School fee summary</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 w-12">Sl No</th>
                        <th class="p-3">School</th><th class="p-3">Items</th><th class="p-3">Total due</th>
                        <th class="p-3">Status</th><th class="p-3">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(s, idx) in schoolSummaries" :key="s.school_id" class="border-t">
                        <td class="p-3 text-slate-400">{{ idx + 1 }}</td>
                        <td class="p-3 font-medium">{{ (s.school_name || '').toUpperCase() }}</td>
                        <td class="p-3">{{ s.item_count }}</td>
                        <td class="p-3 font-semibold">₹{{ s.total_due }}</td>
                        <td class="p-3"><span :class="feeStatusClass(s.fee_status)" class="text-xs font-semibold px-2 py-0.5 rounded">{{ s.fee_status }}</span></td>
                        <td class="p-3 text-xs font-mono">{{ s.receipt_no ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 w-12">Sl No</th>
                        <th class="p-3">{{ event.event_type === 'sports' ? 'Sport Event' : 'Head' }}</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Fest ID</th>
                        <th class="p-3">Item reg</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in displayRows" :key="row.participant_id">
                        <tr v-if="idx === 0 || row.head_name !== displayRows[idx - 1]?.head_name" class="bg-slate-50/80">
                            <td colspan="9" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ row.head_name ?? 'Other items' }}
                            </td>
                        </tr>
                        <tr class="border-t align-top">
                            <td class="p-3 text-xs text-slate-400">{{ idx + 1 }}</td>
                            <td class="p-3 text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                            <td class="p-3 text-xs">{{ (row.school_name || '').toUpperCase() }}</td>
                             <td class="p-3">
                                 <span class="font-medium text-slate-800">{{ row.participant_name }}</span>
                                 <p class="text-xs font-mono text-[#0f3d7a]">{{ row.participant_reg_no }}</p>
                                 <div class="flex flex-wrap gap-1 mt-1 text-[9px]">
                                     <span v-if="row.role" class="px-1 py-0.5 rounded font-bold uppercase tracking-wide"
                                           :class="row.role === 'standby' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
                                         {{ row.role }}
                                     </span>
                                     <span v-if="row.team_name" class="px-1 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">
                                         👥 {{ row.team_name }}
                                     </span>
                                 </div>
                             </td>
                             <td class="p-3 font-mono text-xs font-semibold text-[#0f3d7a]">{{ row.level_reg }}</td>
                             <td class="p-3 font-mono text-xs">{{ row.item_reg }}</td>
                             <td class="p-3 text-xs">
                                 <span class="font-medium text-slate-900">{{ row.item_title }}</span>
                                 <div v-if="row.competition_start && event.event_type === 'sports'" class="mt-0.5">
                                     <span class="inline-flex items-center gap-1 rounded bg-sky-50 px-1 py-0.5 text-[9px] font-bold text-sky-800 border border-sky-100 uppercase tracking-wide">
                                         📅 {{ formatDate(row.competition_start) }}<span v-if="row.competition_time"> @ {{ row.competition_time.slice(0, 5) }}</span>
                                     </span>
                                 </div>
                             </td>
                            <td class="p-3 font-mono text-xs">{{ row.chest_no }}</td>
                            <td class="p-3 text-xs">{{ row.item_fee != null ? `₹${row.item_fee}` : '—' }}</td>
                        </tr>
                    </template>
                    <tr v-if="!displayRows.length">
                        <td colspan="9" class="p-8 text-center text-gray-400">No registrations match filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadFilter from '@/Components/reports/ReportHeadFilter.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, schoolSummaries: Array, totals: Object,
    schools: Object, filterSchoolId: { type: String, default: '' },
    feesUrl: String, activityLogs: { type: Array, default: () => [] },
});

const page = usePage();
const headItemGroups = computed(() => page.props.headItemGroups ?? []);
const headsForFilter = computed(() => page.props.headsForFilter ?? []);
const hasItemHeads = computed(() => page.props.hasItemHeads ?? false);

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/registration-register`;
const params = new URLSearchParams(window.location.search);
const schoolFilter = ref(props.filterSchoolId || params.get('school_id') || '');
const headFilter = ref(params.get('head_id') ?? '');
const itemFilter = ref(params.get('item_id') ?? '');

const displayRows = computed(() => {
    let list = props.rows ?? [];
    if (headFilter.value) {
        list = list.filter((r) => String(r.head_id) === String(headFilter.value));
    }
    if (itemFilter.value) {
        list = list.filter((r) => String(r.item_id) === String(itemFilter.value));
    }
    return list;
});

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    if (schoolFilter.value) q.set('school_id', schoolFilter.value);
    if (headFilter.value) q.set('head_id', headFilter.value);
    if (itemFilter.value) q.set('item_id', itemFilter.value);
    const qs = q.toString();
    return `${base}/export${qs ? `?${qs}` : ''}`;
});

function applyFilter() {
    router.get(base, {
        school_id: schoolFilter.value || undefined,
        head_id: headFilter.value || undefined,
        item_id: itemFilter.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}

function feeStatusClass(status) {
    return {
        approved: 'bg-emerald-100 text-emerald-800',
        proof_uploaded: 'bg-amber-100 text-amber-800',
        rejected: 'bg-red-100 text-red-700',
        pending: 'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}
</script>
