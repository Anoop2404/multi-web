<template>
    <SahodayaEventsLayout :title="`${event.title} — Activity`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Activity log`" eyebrow="Activity log"
                    description="All actions across this event, filterable by item, school, page, or search keyword." />

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" :event="event" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" />

        <!-- Filters Bar -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Search Keywords</label>
                    <input v-model="searchQuery" @input="onSearchInput" type="search" placeholder="Search participant, chest #, item, school..." class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Filter by Item</label>
                    <select v-model="selectedItem" @change="applyFilters" class="w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option :value="null">All Items ({{ items.length }})</option>
                        <option v-for="it in items" :key="it.id" :value="it.id">
                            {{ it.item_code ? `[${it.item_code}] ` : '' }}{{ it.title }}
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
            <EmptyState v-if="!activityLogs.length" title="No activity found" description="No logged actions match your selected item, page, or search query." icon="📋" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-36">When</th>
                            <th class="w-36">Page</th>
                            <th>Action & Details</th>
                            <th class="w-36">User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in activityLogs" :key="log.id" class="hover:bg-slate-50/80 transition">
                            <td class="text-xs text-slate-500 whitespace-nowrap">{{ formatTime(log.created_at) }}</td>
                            <td>
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700 border border-slate-200">
                                    {{ log.page_label }}
                                </span>
                            </td>
                            <td>
                                <div class="text-sm font-medium text-slate-800">{{ log.description }}</div>
                                <div v-if="log.item_title || log.school || log.participant || log.chest_no" class="mt-1.5 flex flex-wrap gap-1.5 text-[11px]">
                                    <span v-if="log.item_title" class="rounded-md bg-amber-50 text-amber-700 px-2 py-0.5 font-semibold border border-amber-200/60">
                                        📌 {{ log.item_title }}
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
                                </div>
                            </td>
                            <td class="text-xs text-slate-600 font-medium whitespace-nowrap">{{ log.user?.name ?? 'System' }}</td>
                        </tr>
                    </tbody>
                </table>
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

const hasActiveFilters = computed(() => !!searchQuery.value || selectedItem.value !== null || selectedPage.value !== null);

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

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
