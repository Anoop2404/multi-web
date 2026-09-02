<template>
    <SchoolAdminLayout :title="`Student-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student-wise — ${event.title}`"
            :eyebrow="programLabel"
            description="Per-student registrations and scores for this event."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" :csv-url="csvUrl" />
            </template>
        </PageHeader>

        <!-- Search & Filter Toolbar -->
        <div class="card !py-3.5 my-4 shadow-sm border border-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[240px]">
                    <div class="relative flex-1 min-w-[220px]">
                        <input v-model="searchQuery"
                               type="text"
                               placeholder="Search student name or admission / reg no..."
                               class="field text-xs pl-8 w-full" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <SearchableSelect v-model="classFilter" class="min-w-[160px]" :options="classOptions" :all-option="true" all-label="All classes" placeholder="All classes" />
                    <button v-if="searchQuery || classFilter" type="button" @click="searchQuery = ''; classFilter = null;" class="text-xs text-slate-400 hover:underline">Clear</button>
                    <span v-if="searchQuery || classFilter" class="text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-lg">
                        {{ filteredRows.length }} of {{ rows.length }}
                    </span>
                </div>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" :csv-url="csvUrl" />
            </div>
        </div>

        <!-- Student Cards List -->
        <div class="space-y-4">
            <div v-for="st in filteredRows" :key="st.student_id" class="card p-0 overflow-hidden shadow-sm border border-slate-200">
                <!-- Card Header -->
                <div class="px-5 py-3.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <!-- Student Photo or Avatar Fallback -->
                        <div class="relative">
                            <img v-if="st.photo_url" :src="st.photo_url" :alt="st.name" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-200" />
                            <div v-else class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold text-sm flex items-center justify-center shadow-sm ring-1 ring-slate-200">
                                {{ (st.name || 'S').charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 text-base flex items-center gap-2">
                                {{ st.name }}
                                <span v-if="st.reg_no" class="text-xs font-mono font-normal text-slate-500">({{ st.reg_no }})</span>
                            </h4>
                            <p class="text-xs text-slate-500 font-medium mt-0.5">🏫 {{ st.school_name || school?.name }}<span v-if="st.class_name"> · Class {{ st.class_name }}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100">
                            {{ st.item_count }} {{ st.item_count === 1 ? 'item' : 'items' }} registered
                        </span>
                        <span v-if="st.total_score > 0" class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                            Total score: {{ st.total_score }}
                        </span>
                    </div>
                </div>

                <!-- Items Breakdown Table -->
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="w-10 text-center">#</th>
                                <th>Item Title</th>
                                <th>Category / Head</th>
                                <th class="text-center">Stage / Type</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Grade / Rank</th>
                                <th class="text-right pr-4">Score</th>
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
                                <td class="text-center text-slate-700 font-medium">
                                    <span v-if="item.position" class="font-bold text-indigo-600">Rank #{{ item.position }}</span>
                                    <span v-if="item.position && item.grade"> · </span>
                                    <span v-if="item.grade" class="font-semibold text-emerald-600">{{ item.grade }} Grade</span>
                                    <span v-if="!item.position && !item.grade">—</span>
                                </td>
                                <td class="text-right pr-4 font-mono font-semibold text-slate-900">
                                    {{ item.score ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="!filteredRows.length" class="card p-12 text-center text-slate-400">
                <p class="font-semibold">No student participants match your search.</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { festItemParticipantTypeLabel as participantTypeLabel } from '@/support/festItemListingMeta.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: { type: Array, default: () => [] },
    pdfUrl: String,
    xlsUrl: String,
    csvUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const searchQuery = ref('');
const classFilter = ref(null);

const classOptions = computed(() => {
    const names = [...new Set(props.rows.map((r) => r.class_name).filter(Boolean))];
    names.sort((a, b) => {
        const numA = parseInt(a, 10);
        const numB = parseInt(b, 10);
        if (!Number.isNaN(numA) && !Number.isNaN(numB) && numA !== numB) return numA - numB;
        return a.localeCompare(b, undefined, { numeric: true });
    });
    return names.map((name) => ({ value: name, label: `Class ${name}` }));
});

const filteredRows = computed(() => {
    const q = searchQuery.value.toLowerCase();
    return props.rows.filter((r) => {
        if (classFilter.value && r.class_name !== classFilter.value) return false;
        if (!q) return true;
        return (r.name && r.name.toLowerCase().includes(q)) ||
            (r.reg_no && r.reg_no.toLowerCase().includes(q));
    });
});
</script>
