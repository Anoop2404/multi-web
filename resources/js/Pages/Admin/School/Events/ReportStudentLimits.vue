<template>
    <SchoolAdminLayout :title="`Student Item Limits — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student Item Limits — ${event.title}`"
            :eyebrow="programLabel"
            description="Per-student on-stage, off-stage, individual (combined) and group item usage vs limits — combined across every phase and region of this fest."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
            </template>
        </PageHeader>

        <!-- Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 my-4">
            <div class="card text-center">
                <p class="text-2xl font-bold text-slate-900">{{ summary.total_students }}</p>
                <p class="text-xs text-gray-500">Students</p>
            </div>
            <div class="card text-center" :class="summary.exceeding_students ? 'border-rose-300 bg-rose-50/60' : ''">
                <p class="text-2xl font-bold" :class="summary.exceeding_students ? 'text-rose-600' : 'text-slate-900'">{{ summary.exceeding_students }}</p>
                <p class="text-xs text-gray-500">Exceeding a limit</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold" :class="summary.exceeding_on_stage ? 'text-rose-600' : 'text-slate-900'">{{ summary.exceeding_on_stage }}</p>
                <p class="text-xs text-gray-500">Over on-stage</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold" :class="summary.exceeding_off_stage ? 'text-rose-600' : 'text-slate-900'">{{ summary.exceeding_off_stage }}</p>
                <p class="text-xs text-gray-500">Over off-stage</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold" :class="summary.exceeding_individual ? 'text-rose-600' : 'text-slate-900'">{{ summary.exceeding_individual }}</p>
                <p class="text-xs text-gray-500">Over individual (combined)</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold" :class="summary.exceeding_group ? 'text-rose-600' : 'text-slate-900'">{{ summary.exceeding_group }}</p>
                <p class="text-xs text-gray-500">Over group</p>
            </div>
        </div>

        <!-- Category / item filters (server-side — narrows which students show and what the -->
        <!-- PDF/CSV export contains) -->
        <div class="card !py-3.5 mb-4 shadow-sm border border-slate-200">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-56">
                    <label class="label-xs">Category</label>
                    <SearchableSelect v-model="categoryFilter" :options="categoryOptions" :all-option="true" all-label="All categories" />
                </div>
                <div class="w-64">
                    <label class="label-xs">Item</label>
                    <SearchableSelect v-model="itemFilter" :options="itemOptions" :all-option="true" all-label="All items" />
                </div>
                <button type="button" class="btn-primary text-sm" @click="applyFilters">Apply</button>
            </div>
        </div>

        <!-- Search Toolbar -->
        <div class="card !py-3.5 mb-4 shadow-sm border border-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="relative flex-1 min-w-[240px]">
                    <input v-model="searchQuery"
                           type="text"
                           placeholder="Search student name or admission / reg no..."
                           class="field text-xs pl-8 w-full" />
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600 font-medium">
                    <input type="checkbox" v-model="onlyExceeding" class="rounded border-slate-300" />
                    Only show students exceeding a limit
                </label>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
            </div>
        </div>

        <!-- Student Cards List -->
        <div class="space-y-4">
            <div v-for="st in filteredRows" :key="st.student_id" class="card p-0 overflow-hidden shadow-sm border"
                 :class="st.exceeds_any ? 'border-rose-300' : 'border-slate-200'">
                <!-- Card Header -->
                <div class="px-5 py-3.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <img v-if="st.photo_url" :src="st.photo_url" :alt="st.name" class="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0" />
                        <div v-else class="w-9 h-9 rounded-full bg-indigo-600 text-white text-sm font-bold flex items-center justify-center shrink-0">
                            {{ (st.name || 'S').charAt(0).toUpperCase() }}
                        </div>
                        <h4 class="font-bold text-slate-900 text-base flex items-center gap-2">
                            {{ st.name }}
                            <span v-if="st.reg_no" class="text-xs font-mono font-normal text-slate-500">({{ st.reg_no }})</span>
                            <span v-if="st.exceeds_any" class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold uppercase tracking-wide border border-rose-200">Exceeds limit</span>
                        </h4>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <LimitBadge label="On-stage" :dim="st.on_stage" />
                        <LimitBadge label="Off-stage" :dim="st.off_stage" />
                        <LimitBadge label="Individual" :dim="st.individual" emphasize />
                        <LimitBadge label="Group" :dim="st.group" />
                        <LimitBadge label="Total" :dim="st.total" />
                        <button type="button"
                                class="px-2.5 py-1 rounded-full text-xs font-bold border inline-flex items-center gap-1.5 bg-white text-slate-600 border-slate-200 hover:bg-slate-50"
                                @click="toggleExpanded(st.student_id)">
                            <svg v-if="!isExpanded(st.student_id)" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23" stroke-width="2"/></svg>
                            {{ isExpanded(st.student_id) ? 'Hide' : 'View' }} items ({{ st.items.length }})
                        </button>
                    </div>
                </div>

                <!-- Items Breakdown Table — collapsed by default, toggled via the eye button above -->
                <div v-if="isExpanded(st.student_id)" class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="w-10 text-center">#</th>
                                <th>Item Title</th>
                                <th>Category</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(item, idx) in st.items" :key="item.item_id || idx" class="hover:bg-slate-50/50">
                                <td class="text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="font-semibold text-slate-900">{{ item.item_title }}</td>
                                <td class="text-slate-500">{{ item.category_label || '—' }}</td>
                                <td class="text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide inline-block bg-slate-100 text-slate-600 border border-slate-200">
                                        {{ dimensionLabel(item.dimension) }}
                                    </span>
                                </td>
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
                <p class="font-semibold">No students match your search.</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref, computed, watch, h } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    categories: { type: Object, default: () => ({}) },
    items: { type: Array, default: () => [] },
    filterCategory: { type: String, default: null },
    filterItemId: { type: Number, default: null },
    csvUrl: String,
    pdfUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const searchQuery = ref('');
const onlyExceeding = ref(false);
const expandedIds = ref(new Set());

// Category → item is a narrowing cascade (Students.vue's class-category → class filter is
// the reference pattern in this codebase): picking a category limits the item dropdown to
// items in it, and switching category clears an item selection that's no longer valid.
const categoryOptions = computed(() => Object.entries(props.categories).map(([value, label]) => ({ value, label })));
const categoryFilter = ref(props.filterCategory ?? '');
const itemFilter = ref(props.filterItemId ? String(props.filterItemId) : '');

const filteredItems = computed(() => {
    if (!categoryFilter.value) return props.items;
    return props.items.filter((i) => i.category_key === categoryFilter.value);
});
const itemOptions = computed(() => filteredItems.value.map((i) => ({ value: String(i.id), label: i.title })));

watch(categoryFilter, () => {
    if (itemFilter.value && ! filteredItems.value.some((i) => String(i.id) === itemFilter.value)) {
        itemFilter.value = '';
    }
});

function applyFilters() {
    router.get(`${programBase.value}/reports/${props.event.id}/student-limits`, {
        category: categoryFilter.value || undefined,
        item_id: itemFilter.value || undefined,
    }, { preserveState: true });
}

function isExpanded(studentId) {
    return expandedIds.value.has(studentId);
}

function toggleExpanded(studentId) {
    const next = new Set(expandedIds.value);
    if (next.has(studentId)) {
        next.delete(studentId);
    } else {
        next.add(studentId);
    }
    expandedIds.value = next;
}

const filteredRows = computed(() => {
    let rows = props.rows;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        rows = rows.filter((r) =>
            (r.name && r.name.toLowerCase().includes(q)) ||
            (r.reg_no && r.reg_no.toLowerCase().includes(q))
        );
    }
    if (onlyExceeding.value) {
        rows = rows.filter((r) => r.exceeds_any);
    }
    return rows;
});

function dimensionLabel(dimension) {
    return { on_stage: 'On-stage', off_stage: 'Off-stage', group: 'Group' }[dimension] ?? '—';
}

const LimitBadge = {
    props: { label: String, dim: Object, emphasize: Boolean },
    setup(props) {
        return () => {
            const dim = props.dim ?? { used: 0, limit: null, exceeds: false };
            const base = 'px-2.5 py-1 rounded-full text-xs font-bold border inline-flex items-center gap-1';
            const tone = dim.exceeds
                ? 'bg-rose-50 text-rose-700 border-rose-200'
                : props.emphasize
                    ? 'bg-indigo-50 text-indigo-700 border-indigo-100'
                    : 'bg-slate-50 text-slate-600 border-slate-200';
            return h('span', { class: `${base} ${tone}` }, [
                `${props.label}: ${dim.used}`,
                dim.limit !== null ? h('span', { class: 'font-normal opacity-70' }, `/ ${dim.limit}`) : null,
            ]);
        };
    },
};
</script>
