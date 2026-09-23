<template>
    <SahodayaEventsLayout :title="`${event.title} — Activity`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Activity log`" eyebrow="Activity log"
                    description="All actions across this event, filterable by item, category, chest #, school, IP, or keywords." />

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" :event="event" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" />

        <!-- Filters Bar -->
        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Search Keywords</label>
                    <input v-model="searchQuery" @input="onSearchInput" type="search" placeholder="Chest #, reg #, participant, school, IP..." class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Filter by Item & Category</label>
                    <SearchableSelect v-model="selectedItem" @change="() => applyFilters(1)" :options="itemOptions" :all-option="true" :all-label="`All Items (${items.length})`" class="w-full" placeholder="All Items" search-placeholder="Search items…" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Filter by Page</label>
                    <SearchableSelect v-model="selectedPage" @change="() => applyFilters(1)" :options="pageOptions" :all-option="true" all-label="All Pages" class="w-full" placeholder="All Pages" search-placeholder="Search pages…" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Logs to Display</label>
                    <select v-model="selectedLimit" @change="changeLimit" class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                        <option value="200">200 per page (default)</option>
                        <option value="500">500 per page</option>
                        <option value="all">View All (up to 5,000)</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button v-if="hasActiveFilters" @click="clearFilters" type="button" class="flex-1 rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition text-center">
                        Reset
                    </button>
                    <button @click="exportCsv" type="button" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition whitespace-nowrap shadow-sm" title="Export matching logs as CSV">
                        <span>📥</span> Export CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination / Summary Top Bar -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-600">
            <div>
                <span v-if="pagination.total > 0">
                    Showing <strong class="text-slate-900 font-semibold">{{ pagination.from }}</strong> to <strong class="text-slate-900 font-semibold">{{ pagination.to }}</strong> of <strong class="text-slate-900 font-semibold">{{ pagination.total.toLocaleString() }}</strong> activities
                    <span v-if="selectedLimit === 'all'" class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">Showing All</span>
                </span>
                <span v-else>
                    No activities recorded
                </span>
            </div>
            <div v-if="pagination.last_page > 1" class="flex items-center gap-2">
                <button
                    @click="goToPage(pagination.current_page - 1)"
                    :disabled="pagination.current_page <= 1"
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    &larr; Prev
                </button>
                <span class="text-xs font-medium text-slate-500">
                    Page <strong class="text-slate-800">{{ pagination.current_page }}</strong> of <strong class="text-slate-800">{{ pagination.last_page }}</strong>
                </span>
                <button
                    @click="goToPage(pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page"
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                    Next &rarr;
                </button>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!displayedLogs.length" title="No activity found" description="No logged actions match your selected item, page, or search query." icon="📋" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-36">When</th>
                            <th class="w-36">Page</th>
                            <th>Action & Details</th>
                            <th class="w-36">User & IP</th>
                            <th class="w-24 text-right">Payload</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in displayedLogs" :key="log.id" class="hover:bg-slate-50/80 transition">
                            <td class="text-xs text-slate-500 whitespace-nowrap">
                                <div class="font-medium text-slate-700">{{ formatTime(log.created_at) }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ formatExactTime(log.created_at) }}</div>
                            </td>
                            <td>
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700 border border-slate-200">
                                    {{ log.page_label }}
                                </span>
                            </td>
                            <td>
                                <div class="text-sm font-medium text-slate-800">{{ log.description }}</div>
                                <div v-if="log.item_title || log.item_category || log.school || log.participant || log.chest_no || log.reg_no" class="mt-1.5 flex flex-wrap gap-1.5 text-[11px]">
                                    <span v-if="log.item_title" class="rounded-md bg-amber-50 text-amber-700 px-2 py-0.5 font-semibold border border-amber-200/60">
                                        📌 {{ log.item_title }}
                                    </span>
                                    <span v-if="log.item_category" class="rounded-md bg-indigo-50 text-indigo-700 px-2 py-0.5 font-semibold border border-indigo-200/60">
                                        📂 {{ log.item_category }}
                                    </span>
                                    <span v-if="log.school" class="rounded-md bg-blue-50 text-blue-700 px-2 py-0.5 font-semibold border border-blue-200/60">
                                        🏫 {{ log.school }}
                                    </span>
                                    <span v-if="log.chest_no" class="rounded-md bg-emerald-50 text-emerald-700 px-2 py-0.5 font-semibold border border-emerald-200/60">
                                        🏷️ Chest #{{ log.chest_no }}
                                    </span>
                                    <span v-if="log.participant" class="rounded-md bg-purple-50 text-purple-700 px-2 py-0.5 font-semibold border border-purple-200/60">
                                        👤 {{ log.participant }}
                                    </span>
                                    <span v-if="log.reg_no" class="rounded-md bg-slate-100 text-slate-700 px-2 py-0.5 font-semibold border border-slate-200 font-mono">
                                        🆔 {{ log.reg_no }}
                                    </span>
                                    <span v-if="log.properties?.status === 'absent'" class="rounded-md bg-red-50 text-red-700 px-2 py-0.5 font-bold border border-red-200/60">
                                        🔴 ABSENT
                                    </span>
                                    <span v-if="log.properties?.status === 'present'" class="rounded-md bg-emerald-50 text-emerald-700 px-2 py-0.5 font-bold border border-emerald-200/60">
                                        🟢 PRESENT
                                    </span>
                                </div>
                                <div v-if="log.reason" class="mt-1.5 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-1 inline-block">
                                    <span class="font-semibold">Reason:</span> {{ log.reason }}
                                </div>
                            </td>
                            <td class="text-xs text-slate-600 font-medium whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ log.user?.name ?? 'System' }}</span>
                                    <span v-if="log.actor_type"
                                          class="rounded px-1.5 py-0.5 text-[10px] font-bold border"
                                          :class="log.actor_type === 'School'
                                              ? 'bg-amber-50 text-amber-700 border-amber-200'
                                              : 'bg-indigo-50 text-indigo-700 border-indigo-200'">
                                        {{ log.actor_type === 'School' ? 'SCHOOL' : 'SAHODAYA' }}
                                    </span>
                                </div>
                                <div v-if="log.ip_address" class="text-[10px] text-slate-400 font-mono">🌐 {{ log.ip_address }}</div>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button @click="openPayloadModal(log)" type="button" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                                    🔍 Data
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Pagination Bar -->
            <div v-if="pagination.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/70 px-4 py-3 text-xs text-slate-600">
                <div>
                    Showing <strong class="text-slate-900 font-semibold">{{ pagination.from }}</strong> to <strong class="text-slate-900 font-semibold">{{ pagination.to }}</strong> of <strong class="text-slate-900 font-semibold">{{ pagination.total.toLocaleString() }}</strong> total activities
                </div>
                <div v-if="pagination.last_page > 1" class="flex items-center gap-1.5">
                    <button
                        @click="goToPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        &larr; Prev
                    </button>
                    <div class="flex items-center gap-1">
                        <template v-for="p in visiblePages" :key="p">
                            <span v-if="p === '...'" class="px-1.5 text-slate-400 font-bold">...</span>
                            <button
                                v-else
                                @click="goToPage(p)"
                                type="button"
                                class="min-w-8 rounded-lg px-2.5 py-1 text-xs font-semibold shadow-sm transition"
                                :class="p === pagination.current_page ? 'bg-amber-600 text-white font-bold' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'">
                                {{ p }}
                            </button>
                        </template>
                    </div>
                    <button
                        @click="goToPage(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page"
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        Next &rarr;
                    </button>
                </div>
            </div>
        </div>

        <!-- Payload Modal -->
        <div v-if="selectedLog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Submitted Action Payload</h3>
                        <p class="text-xs text-slate-500">{{ selectedLog.description }}</p>
                    </div>
                    <button @click="selectedLog = null" type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        ✕
                    </button>
                </div>

                <div class="my-4 overflow-y-auto space-y-4 pr-1 text-xs">
                    <!-- Core Metadata -->
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 border border-slate-200">
                        <div>
                            <span class="text-slate-400 font-medium">Timestamp:</span>
                            <div class="font-semibold text-slate-700 font-mono">{{ formatExactTime(selectedLog.created_at) }}</div>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium">IP Address:</span>
                            <div class="font-semibold text-slate-700 font-mono">🌐 {{ selectedLog.ip_address ?? '—' }}</div>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium">User:</span>
                            <div class="font-semibold text-slate-700">{{ selectedLog.user?.name ?? 'System' }} ({{ selectedLog.user?.email ?? '—' }})</div>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium">Action Key:</span>
                            <div class="font-semibold text-slate-700 font-mono">{{ selectedLog.action }}</div>
                        </div>
                    </div>

                    <!-- Submitted Mark & Score Summary Card -->
                    <div v-if="selectedLog.properties?.score !== undefined || selectedLog.properties?.grade || selectedLog.properties?.position" class="rounded-xl bg-amber-50/70 border border-amber-200 p-4">
                        <h4 class="font-bold text-amber-900 mb-2 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                            📊 Submitted Mark & Score Data
                        </h4>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-lg bg-white p-2 border border-amber-200/60 shadow-sm">
                                <div class="text-[10px] uppercase font-bold text-amber-700">Rank / Position</div>
                                <div class="text-base font-extrabold text-amber-900">{{ selectedLog.properties?.position ? `#${selectedLog.properties.position}` : '—' }}</div>
                            </div>
                            <div class="rounded-lg bg-white p-2 border border-amber-200/60 shadow-sm">
                                <div class="text-[10px] uppercase font-bold text-amber-700">Total Score</div>
                                <div class="text-base font-extrabold text-amber-900">{{ selectedLog.properties?.score !== null && selectedLog.properties?.score !== undefined ? selectedLog.properties.score : '—' }}</div>
                            </div>
                            <div class="rounded-lg bg-white p-2 border border-amber-200/60 shadow-sm">
                                <div class="text-[10px] uppercase font-bold text-amber-700">Grade</div>
                                <div class="text-base font-extrabold text-amber-900">{{ selectedLog.properties?.grade ? `Grade ${selectedLog.properties.grade}` : '—' }}</div>
                            </div>
                        </div>
                        <div v-if="selectedLog.properties?.judge_scores" class="mt-3 text-xs text-amber-900">
                            <span class="font-semibold text-amber-800">Judge Breakdown: </span>
                            <span class="font-mono bg-white px-2 py-0.5 rounded border border-amber-200 text-amber-950 font-bold">
                                {{ Array.isArray(selectedLog.properties.judge_scores) ? selectedLog.properties.judge_scores.join(', ') : JSON.stringify(selectedLog.properties.judge_scores) }}
                            </span>
                        </div>
                    </div>

                    <!-- Submitted Properties / Post Data -->
                    <div>
                        <h4 class="font-bold text-slate-700 mb-2 uppercase tracking-wider text-[10px]">Submitted Post Data & Properties</h4>
                        <div v-if="selectedLog.properties && Object.keys(selectedLog.properties).length" class="rounded-xl border border-slate-200 bg-slate-900 p-4 font-mono text-[11px] text-emerald-400 overflow-x-auto">
                            <pre>{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
                        </div>
                        <p v-else class="text-slate-400 italic">No extra post parameters were recorded for this action.</p>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 flex justify-end">
                    <button @click="selectedLog = null" type="button" class="rounded-xl bg-slate-800 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, activityLogs: { type: Array, default: () => [] },
    pageLabels: Object,
    items: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    pagination: { type: Object, default: () => ({ total: 0, per_page: 200, current_page: 1, last_page: 1, from: 0, to: 0 }) },
});

const searchQuery = ref(props.filters?.q ?? '');
const selectedItem = ref(props.filters?.item_id ?? null);
const selectedPage = ref(props.filters?.page ?? null);
const selectedLimit = ref(props.filters?.limit ?? '200');

const selectedLog = ref(null);

const hasActiveFilters = computed(() => {
    const q = searchQuery.value?.trim();
    return (!!q && q.toLowerCase() !== 'all')
        || selectedItem.value !== null
        || selectedPage.value !== null
        || selectedLimit.value !== '200';
});

const itemOptions = computed(() => props.items.map(it => ({
    value: it.id,
    label: `${it.item_code ? `[${it.item_code}] ` : ''}${it.title}${it.category ? ` — ${it.category}` : ''}`,
})));

const pageOptions = computed(() => Object.entries(props.pageLabels || {}).map(([key, label]) => ({ value: key, label })));

const visiblePages = computed(() => {
    const total = props.pagination?.last_page || 1;
    const current = props.pagination?.current_page || 1;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const pages = [];
    pages.push(1);
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
});

const displayedLogs = computed(() => {
    let logs = props.activityLogs || [];
    if (!logs.length) return [];

    const raw = searchQuery.value ? searchQuery.value.trim() : '';
    // If user searched for literal "all", don't filter out all items
    if (raw && raw.toLowerCase() !== 'all') {
        const terms = raw.toLowerCase().split(/\s+/);
        logs = logs.filter(log => {
            const text = [
                log.description,
                log.participant,
                log.chest_no ? `chest #${log.chest_no}` : '',
                log.chest_no,
                log.school,
                log.reg_no,
                log.item_title,
                log.item_code,
                log.item_category,
                log.page_label,
                log.user?.name,
                log.actor_type,
                log.ip_address,
                log.reason,
            ].filter(Boolean).join(' ').toLowerCase();

            return terms.every(term => text.includes(term));
        });
    }

    return logs;
});

let searchTimeout = null;
function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(1), 350);
}

function applyFilters(page = 1) {
    const qVal = searchQuery.value?.trim();
    router.get(
        window.location.pathname,
        {
            q: (qVal && qVal.toLowerCase() !== 'all') ? qVal : undefined,
            item_id: selectedItem.value || undefined,
            page: selectedPage.value || undefined,
            limit: selectedLimit.value !== '200' ? selectedLimit.value : undefined,
            p: page > 1 ? page : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function goToPage(p) {
    if (p < 1 || p > (props.pagination?.last_page || 1)) return;
    applyFilters(p);
}

function changeLimit() {
    applyFilters(1);
}

function clearFilters() {
    searchQuery.value = '';
    selectedItem.value = null;
    selectedPage.value = null;
    selectedLimit.value = '200';
    applyFilters(1);
}

function exportCsv() {
    const qVal = searchQuery.value?.trim();
    const params = new URLSearchParams();
    params.set('export', 'csv');
    if (qVal && qVal.toLowerCase() !== 'all') params.set('q', qVal);
    if (selectedItem.value) params.set('item_id', selectedItem.value);
    if (selectedPage.value) params.set('page', selectedPage.value);

    window.location.href = `${window.location.pathname}?${params.toString()}`;
}

function openPayloadModal(log) {
    selectedLog.value = log;
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatExactTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
</script>
