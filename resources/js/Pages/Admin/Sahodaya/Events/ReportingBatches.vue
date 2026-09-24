<template>
    <SahodayaEventsLayout :title="`${event.title} — Reporting Batches`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Reporting Batches`" eyebrow="Schedule"
                    :description="`Pick an item with more than ${minRegistrations} registrations, then split its teams/participants into ordered batches so they don't all report at once — Batch 1 reports/performs first, then Batch 2, and so on.`">
            <template #actions>
                <Link :href="`${eventBase}/school-distances`" class="btn-secondary text-sm">📏 School distances</Link>
                <button type="button" class="btn-secondary text-sm" @click="showSettings = true">⚙️ Settings</button>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="reporting-batches" class="mb-4" />

        <div v-if="items.length === 0" class="card text-sm text-slate-400">
            No items have more than {{ minRegistrations }} registrations yet — batching isn't needed until an item gets that large.
        </div>

        <div v-else class="grid lg:grid-cols-[320px_1fr] gap-6 items-start">
            <div class="card !p-2 space-y-1 lg:sticky lg:top-4">
                <h4 class="section-title !mb-1 px-2 pt-1">Items ({{ items.length }})</h4>
                <p class="text-xs text-slate-400 px-2 pb-1">Sorted alphabetically.</p>
                <button v-for="item in items" :key="item.id" type="button"
                        class="w-full text-left rounded-lg px-3 py-2 text-sm transition"
                        :class="selectedItem?.id === item.id ? 'bg-[#0f3d7a] text-white' : 'hover:bg-slate-50 text-slate-700'"
                        @click="jumpToItem(item.id)">
                    <div class="font-semibold truncate">{{ item.title }}</div>
                    <div class="text-xs mb-1.5" :class="selectedItem?.id === item.id ? 'text-white/70' : 'text-slate-400'">
                        {{ item.item_code || '' }}
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold border"
                              :class="selectedItem?.id === item.id ? 'bg-white/10 text-white border-white/20' : 'bg-slate-100 text-slate-600 border-slate-200'">
                            {{ item.registration_count }} total
                        </span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold border"
                              :class="selectedItem?.id === item.id ? 'bg-white/10 text-white border-white/20' : 'bg-indigo-50 text-indigo-700 border-indigo-100'">
                            {{ item.batch_count }} batch{{ item.batch_count === 1 ? '' : 'es' }}
                        </span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold border"
                              :class="selectedItem?.id === item.id ? 'bg-white/10 text-white border-white/20' : 'bg-emerald-50 text-emerald-700 border-emerald-100'">
                            {{ item.assigned_count }} assigned
                        </span>
                        <span v-if="item.unassigned_count > 0" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold border"
                              :class="selectedItem?.id === item.id ? 'bg-white/10 text-white border-white/20' : 'bg-amber-50 text-amber-700 border-amber-100'">
                            {{ item.unassigned_count }} unassigned
                        </span>
                    </div>
                </button>
            </div>

            <div v-if="!selectedItem" class="card text-sm text-slate-400">
                Pick an item on the left to view or assign its reporting batches.
            </div>

            <div v-else class="card space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h4 class="section-title">{{ selectedItem.title }}</h4>
                        <p class="section-desc">Listed in report order — Batch 1 reports/performs first.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="`${base}/batch-master?item_id=${selectedItem.id}`" class="btn-secondary text-sm" target="_blank">Open full page ↗</Link>
                        <a :href="`${base}/print?item_id=${selectedItem.id}&preview=1`" class="btn-secondary text-sm" target="_blank">👁 Preview PDF</a>
                        <a :href="`${base}/print?item_id=${selectedItem.id}`" class="btn-secondary text-sm">⬇ Download PDF</a>
                    </div>
                </div>

                <BatchMasterList :selected-item="selectedItem" :batches="batches" :registrations="registrations"
                                  :assign-url="`${base}/assign`" :create-url="base" :batch-base-url="base"
                                  selectable v-model="selectedRegIds" searchable v-model:search="regSearch" />
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <Modal :show="showSettings" title="Reporting batch settings" @close="showSettings = false">
            <label class="text-sm text-slate-600 block">
                Only offer items with more than
                <input v-model.number="settingsForm.reporting_batch_min_registrations" type="number" min="0" class="field text-sm mt-1 w-full">
                registrations for batching.
            </label>
            <template #footer>
                <button type="button" class="btn-ghost text-sm" @click="showSettings = false">Cancel</button>
                <button type="button" class="btn-primary text-sm" :disabled="settingsForm.processing" @click="saveSettings">
                    {{ settingsForm.processing ? 'Saving…' : '💾 Save' }}
                </button>
            </template>
        </Modal>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import BatchMasterList from '@/Components/sahodaya/BatchMasterList.vue';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    items: { type: Array, default: () => [] },
    selectedItem: { type: Object, default: null },
    selectedItemId: { type: [String, Number], default: null },
    batches: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    minRegistrations: { type: Number, default: 15 },
});

const eventBase = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const base = `${eventBase}/reporting-batches`;
const minRegistrations = props.minRegistrations;

const selectedRegIds = ref([]);
const regSearch = ref('');
const showSettings = ref(false);

const settingsForm = useForm({ reporting_batch_min_registrations: props.minRegistrations });

function saveSettings() {
    settingsForm.post(`${base}/settings`, {
        preserveScroll: true,
        onSuccess: () => { showSettings.value = false; },
    });
}

function jumpToItem(itemId) {
    selectedRegIds.value = [];
    if (!itemId) {
        router.get(base, {}, { preserveState: true });
        return;
    }
    router.get(base, { item_id: itemId }, { preserveState: true });
}
</script>
