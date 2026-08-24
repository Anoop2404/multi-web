<template>
    <SahodayaEventsLayout :title="`${event.title} — Activity`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Activity log`" eyebrow="Activity log"
                    description="All actions across this event, filterable by item, category, chest #, school, IP, or keywords." />

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" :event="event" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" />

        <!-- Filters Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Search Keywords</label>
                    <input v-model="searchQuery" @input="onSearchInput" type="search" placeholder="Search chest #, reg ID, participant, school, IP..." class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Filter by Item & Category</label>
                    <select v-model="selectedItem" @change="applyFilters" class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option :value="null">All Items ({{ items.length }})</option>
                        <option v-for="it in items" :key="it.id" :value="it.id">
                            {{ it.item_code ? `[${it.item_code}] ` : '' }}{{ it.title }}{{ it.category ? ` — ${it.category}` : '' }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Filter by Page</label>
                    <select v-model="selectedPage" @change="applyFilters" class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option :value="null">All Pages</option>
                        <option v-for="(label, key) in pageLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button v-if="hasActiveFilters" @click="clearFilters" type="button" class="w-full rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                        Reset Filters
                    </button>
                </div>
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
                            </td>
                            <td class="text-xs text-slate-600 font-medium whitespace-nowrap">
                                <div>{{ log.user?.name ?? 'System' }}</div>
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

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, activityLogs: { type: Array, default: () => [] },
    pageLabels: Object,
    items: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const searchQuery = ref(props.filters?.q ?? '');
const selectedItem = ref(props.filters?.item_id ?? null);
const selectedPage = ref(props.filters?.page ?? null);

const selectedLog = ref(null);

const hasActiveFilters = computed(() => !!searchQuery.value || selectedItem.value !== null || selectedPage.value !== null);

const displayedLogs = computed(() => {
    let logs = props.activityLogs || [];
    if (!logs.length) return [];

    if (searchQuery.value && searchQuery.value.trim()) {
        const terms = searchQuery.value.toLowerCase().trim().split(/\s+/);
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
                log.ip_address,
            ].filter(Boolean).join(' ').toLowerCase();

            return terms.every(term => text.includes(term));
        });
    }

    return logs;
});

let searchTimeout = null;
function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
}

function applyFilters() {
    router.get(
        window.location.pathname,
        {
            q: searchQuery.value || undefined,
            item_id: selectedItem.value || undefined,
            page: selectedPage.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function clearFilters() {
    searchQuery.value = '';
    selectedItem.value = null;
    selectedPage.value = null;
    applyFilters();
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
