<template>
    <SahodayaEventsLayout :title="`${event.title} — Student-wise report`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Student-wise participant report`" eyebrow="Reports"
                    description="View all registered student participants, their school, photos, and registered competition items.">
            <template #actions>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="student-wise" />

        <!-- Region Switcher -->
        <div v-if="childEvents.length" class="card mb-6 !py-3.5 border-l-4 border-l-indigo-600 bg-gradient-to-r from-slate-50 to-white shadow-sm">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-md bg-indigo-50 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h1.5a2.5 2.5 0 002.5-2.5V7.865M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        {{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}
                    </label>
                </div>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="sportEventOptions" :all-option="false" class="w-72" />
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div class="card mb-6 !py-4 shadow-sm border border-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[280px]">
                    <div class="relative flex-1 min-w-[200px]">
                        <input v-model="searchQuery"
                               type="text"
                               placeholder="Search student name or reg no..."
                               class="field text-xs pl-8 w-full"
                               @keyup.enter="applyFilters" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <SearchableSelect v-if="schools?.length" v-model="selectedSchoolId" class="w-64"
                                      :options="schools" :all-label="`All Schools (${schools.length})`"
                                      @change="applyFilters" />
                    <button type="button" @click="applyFilters" class="btn-secondary text-xs">Filter</button>
                    <button v-if="searchQuery || selectedSchoolId" type="button" @click="clearFilters" class="btn-subtle text-xs text-slate-500">Clear</button>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5 text-xs text-slate-600">
                        <span>Show:</span>
                        <SearchableSelect v-model="perPage" :all-option="false"
                                          :options="[{ value: 25, label: '25' }, { value: 50, label: '50' }, { value: 100, label: '100' }, { value: 'all', label: 'All' }]" />
                    </div>
                    <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
                </div>
            </div>
        </div>

        <!-- Student Cards List -->
        <div class="space-y-4">
            <div v-for="(st, index) in paginatedRows" :key="st.student_id" class="card p-0 overflow-hidden shadow-sm border border-slate-200 hover:border-slate-300 transition-all">
                <!-- Card Header -->
                <div class="px-5 py-3.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3.5">
                        <span class="shrink-0 w-7 h-7 rounded-full bg-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center">
                            {{ pageOffset + index + 1 }}
                        </span>
                        <!-- Photo or Avatar -->
                        <div class="relative">
                            <img v-if="st.photo_url" :src="st.photo_url" :alt="st.name" class="w-11 h-11 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-200" />
                            <div v-else class="w-11 h-11 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 text-white font-bold text-base flex items-center justify-center shadow-sm ring-1 ring-slate-200">
                                {{ (st.name || 'S').charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 text-base flex items-center gap-2">
                                {{ st.name }}
                                <span v-if="st.reg_no" class="text-xs font-mono font-normal text-slate-500">({{ st.reg_no }})</span>
                            </h4>
                            <p class="text-xs text-slate-600 font-medium mt-0.5 flex items-center gap-1.5">
                                <span>🏫 {{ st.school_name || '—' }}<template v-if="st.school_code"> ({{ st.school_code }})</template></span>
                                <span v-if="st.gender" class="text-slate-400">·</span>
                                <span v-if="st.gender" class="capitalize text-slate-500">{{ st.gender }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100">
                            {{ st.item_count }} {{ st.item_count === 1 ? 'item' : 'items' }} registered
                        </span>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="w-10 text-center">#</th>
                                <th>Item Title</th>
                                <th>Category / Head</th>
                                <th class="text-center">Stage / Type</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Rank</th>
                                <th class="text-center">Mark</th>
                                <th class="text-center">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(item, idx) in st.items" :key="item.item_id || idx" class="hover:bg-slate-50/50">
                                <td class="text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="font-semibold text-slate-900">{{ item.item_title }}</td>
                                <td class="text-slate-600">
                                    <span v-if="item.category_label">{{ item.category_label }}</span>
                                    <span v-if="item.category_label && item.head_name" class="text-slate-300"> · </span>
                                    <span v-if="item.head_name">{{ item.head_name }}</span>
                                    <span v-if="!item.category_label && !item.head_name">—</span>
                                </td>
                                <td class="text-center text-slate-600 whitespace-nowrap">
                                    <span v-if="item.stage_type">{{ item.stage_type === 'on_stage' ? 'On stage' : 'Off stage' }}</span>
                                    <span v-if="item.stage_type && item.participant_type" class="text-slate-300"> · </span>
                                    <span v-if="item.participant_type">{{ participantTypeLabel(item.participant_type) }}</span>
                                    <span v-if="!item.stage_type && !item.participant_type">—</span>
                                </td>
                                <td class="text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide inline-block"
                                          :class="item.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
                                        {{ item.status || '—' }}
                                    </span>
                                </td>
                                <template v-if="item.results_published">
                                    <td class="text-center font-bold text-slate-900">{{ item.position ?? '—' }}</td>
                                    <td class="text-center text-slate-700">{{ item.score ?? '—' }}</td>
                                    <td class="text-center font-bold text-slate-900">{{ item.grade ?? '—' }}</td>
                                </template>
                                <template v-else>
                                    <td class="text-center text-slate-400" colspan="3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide inline-block bg-slate-100 text-slate-500 border border-slate-200">
                                            Result Pending
                                        </span>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="!filteredRows.length" class="card p-12 text-center text-slate-400">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <p class="font-semibold">No student participants match the selected filters.</p>
            </div>

            <!-- Pagination Footer -->
            <div v-if="totalPages > 1" class="card !py-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600">
                <span>
                    Showing {{ pageOffset + 1 }} to {{ Math.min(pageOffset + perPageNum, filteredRows.length) }} of {{ filteredRows.length }} students
                </span>
                <div class="flex items-center gap-1">
                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded border border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50">
                        Previous
                    </button>
                    <span class="px-2 font-medium">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded border border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { festItemParticipantTypeLabel as participantTypeLabel } from '@/support/festItemListingMeta.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    schools: { type: Array, default: () => [] },
    pdfUrl: String,
    xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const searchQuery = ref(props.filters.search || '');
const selectedSchoolId = ref(props.filters.school_id || null);

const filteredRows = computed(() => {
    let result = props.rows;
    if (selectedSchoolId.value) {
        result = result.filter((r) => String(r.school_id) === String(selectedSchoolId.value));
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter((r) =>
            (r.name && r.name.toLowerCase().includes(q)) ||
            (r.reg_no && r.reg_no.toLowerCase().includes(q))
        );
    }
    return result;
});

const perPage = ref(25);
const currentPage = ref(1);

const perPageNum = computed(() => perPage.value === 'all' ? filteredRows.value.length || 1 : Number(perPage.value));
const totalPages = computed(() => Math.ceil(filteredRows.value.length / perPageNum.value) || 1);
const pageOffset = computed(() => (currentPage.value - 1) * perPageNum.value);

const paginatedRows = computed(() => {
    if (perPage.value === 'all') return filteredRows.value;
    return filteredRows.value.slice(pageOffset.value, pageOffset.value + perPageNum.value);
});

watch([searchQuery, selectedSchoolId, perPage], () => {
    currentPage.value = 1;
});

const sportEventOptions = computed(() => props.childEvents.map((ev) => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/student-wise`);
}

function applyFilters() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/student-wise`,
        {
            search: searchQuery.value || undefined,
            school_id: selectedSchoolId.value || undefined,
        },
        { preserveState: true, replace: true }
    );
}

function clearFilters() {
    searchQuery.value = '';
    selectedSchoolId.value = null;
    applyFilters();
}
</script>
