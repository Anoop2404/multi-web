<template>
    <SahodayaEventsLayout :title="`${event.title} — Batch Master`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Batch Master`" eyebrow="Schedule"
                    :description="`${selectedItem.title} — every registration grouped by reporting batch.`">
            <template #actions>
                <Link :href="`${base}/reporting-batches?item_id=${selectedItem.id}`" class="btn-secondary text-sm">&larr; Back to Reporting Batches</Link>
                <label class="text-xs text-slate-500 flex items-center gap-1">
                    per batch
                    <input v-model.number="downloadBatchSize" type="number" min="1" class="field !py-1 !text-xs !w-16">
                </label>
                <button type="button" class="btn-secondary text-sm" :disabled="printBusy" @click="printWithAutoAssign(true)">👁 Preview PDF</button>
                <button type="button" class="btn-primary text-sm" :disabled="printBusy" @click="printWithAutoAssign(false)">⬇ Download PDF</button>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="reporting-batches" class="mb-4" />

        <div class="card">
            <BatchMasterList :selected-item="selectedItem" :batches="batches" :registrations="registrations"
                              :assign-url="`${base}/reporting-batches/assign`" :auto-assign-url="`${base}/reporting-batches/auto-assign`"
                              :batch-size="batchSize" :batch-base-url="`${base}/reporting-batches`"
                              selectable v-model="selectedRegIds" searchable v-model:search="search" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import BatchMasterList from '@/Components/sahodaya/BatchMasterList.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    selectedItem: { type: Object, required: true },
    batches: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    batchSize: { type: Number, default: 8 },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const search = ref('');
const selectedRegIds = ref([]);
const { confirm } = useConfirm();

const downloadBatchSize = ref(props.batchSize);
const printBusy = ref(false);
watch(() => props.batchSize, (v) => { downloadBatchSize.value = v; });

async function printWithAutoAssign(preview) {
    const size = downloadBatchSize.value || props.batchSize;
    if (!(await confirm({
        message: `Re-assign ALL ${props.registrations.length} registration(s) for this item into batches of ${size}, closest school first, then generate the PDF? Existing assignments for this item will be overwritten.`,
    }))) return;

    printBusy.value = true;
    router.post(`${base}/reporting-batches/auto-assign`, {
        item_id: props.selectedItem.id,
        batch_size: size,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const url = `${base}/reporting-batches/print?item_id=${props.selectedItem.id}${preview ? '&preview=1' : ''}`;
            window.open(url, '_blank');
        },
        onFinish: () => { printBusy.value = false; },
    });
}
</script>
