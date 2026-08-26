<template>
    <SahodayaEventsLayout :title="`${event.title} — Category-wise Points`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Category-wise Points`" eyebrow="Reports"
                    description="Items grouped by category — open an item to see each participant's rank, grade, and how their points break down." />

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="category-wise-points" />

        <div v-if="childEvents.length" class="card mb-4 !py-3 flex flex-wrap items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Region:</label>
            <SearchableSelect :model-value="String(event.id)" @update:model-value="switchEvent" :options="childEventOptions" :all-option="false" class="w-64" />
        </div>

        <div v-if="!categories.length" class="card p-8 text-center text-slate-400 text-sm">
            No categorized items found for this event.
        </div>

        <template v-else>
            <div class="flex flex-wrap gap-2 mb-5" role="tablist">
                <button v-for="cat in categories" :key="cat.key" type="button" role="tab"
                        class="px-4 py-2 rounded-xl text-sm font-semibold border transition"
                        :class="activeKey === cat.key
                            ? 'bg-slate-900 text-white border-slate-900'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                        @click="activeKey = cat.key">
                    {{ cat.label }}
                    <span class="ml-1 text-xs opacity-70">({{ cat.items.length }})</span>
                </button>
            </div>

            <div v-if="activeCategory" class="card card--flush overflow-hidden">
                <div class="px-5 py-3 border-b bg-slate-50/80">
                    <h3 class="section-title text-sm !mb-0">{{ activeCategory.label }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Item Code</th>
                                <th>Type</th>
                                <th class="text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in activeCategory.items" :key="item.id">
                                <td class="font-medium text-slate-900">{{ item.title }}</td>
                                <td class="font-mono text-xs text-slate-500">{{ item.item_code ?? '—' }}</td>
                                <td class="text-xs capitalize text-slate-600">{{ item.participant_type ?? '—' }}</td>
                                <td class="text-right">
                                    <button type="button"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:text-slate-900 hover:border-slate-300 transition"
                                            title="View points breakdown"
                                            @click="openBreakdown(item)">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!activeCategory.items.length">
                                <td colspan="4" class="p-8 text-center text-slate-400">No items in this category.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <CategoryPointsBreakdownModal :open="modalOpen" :fetch-url="modalFetchUrl" :item-title="modalItemTitle" @close="closeBreakdown" />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import CategoryPointsBreakdownModal from '@/Components/reports/CategoryPointsBreakdownModal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    categories: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/category-wise-points`;

const activeKey = ref(props.categories[0]?.key ?? null);
const activeCategory = computed(() => props.categories.find((c) => c.key === activeKey.value) ?? null);

const childEventOptions = computed(() => props.childEvents.map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })));

function switchEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/category-wise-points`);
}

const modalOpen = ref(false);
const modalFetchUrl = ref(null);
const modalItemTitle = ref('');

function openBreakdown(item) {
    modalFetchUrl.value = `${base}/${item.id}/participants`;
    modalItemTitle.value = item.title;
    modalOpen.value = true;
}

function closeBreakdown() {
    modalOpen.value = false;
    modalFetchUrl.value = null;
}
</script>
