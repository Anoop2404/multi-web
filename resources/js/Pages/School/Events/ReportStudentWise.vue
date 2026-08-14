<template>
    <SchoolAdminLayout :title="`${event.title} — Student-wise report`">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <PageHeader :title="`${event.title} — Student-wise report`" eyebrow="Reports"
                        description="All student participants registered by your school and their registered items.">
                <template #actions>
                    <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
                </template>
            </PageHeader>

            <!-- Search Filter -->
            <div class="card !py-3.5 shadow-sm border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="relative flex-1 min-w-[240px]">
                        <input v-model="searchQuery"
                               type="text"
                               placeholder="Search student name..."
                               class="field text-xs pl-8 w-full" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
                </div>
            </div>

            <!-- Student List -->
            <div class="space-y-4">
                <div v-for="st in filteredRows" :key="st.student_id" class="card p-0 overflow-hidden shadow-sm border border-slate-200">
                    <div class="px-5 py-3.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <img v-if="st.photo_url" :src="st.photo_url" :alt="st.name" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-200" />
                            <div v-else class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold text-sm flex items-center justify-center shadow-sm ring-1 ring-slate-200">
                                {{ (st.name || 'S').charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-base">
                                    {{ st.name }}
                                    <span v-if="st.reg_no" class="text-xs font-mono font-normal text-slate-500">({{ st.reg_no }})</span>
                                </h4>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">🏫 {{ school?.name }}</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100">
                            {{ st.item_count }} {{ st.item_count === 1 ? 'item' : 'items' }} registered
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="data-table w-full text-xs">
                            <thead class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                                <tr>
                                    <th class="w-10 text-center">#</th>
                                    <th>Item Title</th>
                                    <th>Category / Head</th>
                                    <th class="text-center">Chest No</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(item, idx) in st.items" :key="item.item_id || idx" class="hover:bg-slate-50/50">
                                    <td class="text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                                    <td class="font-semibold text-slate-900">{{ item.item_title }}</td>
                                    <td class="text-slate-600">{{ item.head_name || '—' }}</td>
                                    <td class="text-center font-mono font-bold text-slate-800">{{ item.chest_no || '—' }}</td>
                                    <td class="text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide inline-block"
                                              :class="item.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
                                            {{ item.status || '—' }}
                                        </span>
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
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';

const props = defineProps({
    program: String,
    school: Object,
    event: Object,
    rows: { type: Array, default: () => [] },
    pdfUrl: String,
    xlsUrl: String,
});

const searchQuery = ref('');

const filteredRows = computed(() => {
    if (!searchQuery.value) return props.rows;
    const q = searchQuery.value.toLowerCase();
    return props.rows.filter((r) =>
        (r.name && r.name.toLowerCase().includes(q)) ||
        (r.reg_no && r.reg_no.toLowerCase().includes(q))
    );
});
</script>
