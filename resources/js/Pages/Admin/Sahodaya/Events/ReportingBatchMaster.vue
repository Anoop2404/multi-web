<template>
    <SahodayaEventsLayout :title="`${event.title} — Batch Master`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Batch Master`" eyebrow="Schedule"
                    :description="`${selectedItem.title} — every registration grouped by reporting batch.`">
            <template #actions>
                <Link :href="`${base}/reporting-batches?item_id=${selectedItem.id}`" class="btn-secondary text-sm">&larr; Back to Reporting Batches</Link>
                <a :href="`${base}/reporting-batches/print?item_id=${selectedItem.id}&preview=1`" class="btn-secondary text-sm" target="_blank">👁 Preview PDF</a>
                <a :href="`${base}/reporting-batches/print?item_id=${selectedItem.id}`" class="btn-primary text-sm">⬇ Download PDF</a>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="reporting-batches" class="mb-4" />

        <div class="card">
            <BatchMasterList :selected-item="selectedItem" :batches="batches" :registrations="registrations"
                              :assign-url="`${base}/reporting-batches/assign`" :create-url="`${base}/reporting-batches`"
                              :batch-base-url="`${base}/reporting-batches`"
                              selectable v-model="selectedRegIds" searchable v-model:search="search" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import BatchMasterList from '@/Components/sahodaya/BatchMasterList.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    selectedItem: { type: Object, required: true },
    batches: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const search = ref('');
const selectedRegIds = ref([]);
</script>
