<template>
    <SahodayaEventsLayout :title="`${event.title} — Import Marks`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Import Marks`" eyebrow="Scoring"
                    description="Bulk import marks from CSV." />
        <div class="card max-w-xl space-y-3">
            <p class="text-sm text-gray-600">Use participant_id or reg_no (+ optional chest_no / item_title). Template includes approved participants.</p>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/marks/import-template`"
               class="inline-block text-xs font-semibold text-indigo-600">Download CSV template</a>
            <form @submit.prevent="submit" class="space-y-3">
                <input type="file" accept=".csv,text/csv" @change="onFile" class="text-sm" required>
                <button class="btn-primary">Import marks</button>
            </form>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object,
    activityLogs: { type: Array, default: () => [] },
});

const form = useForm({ file: null });

function onFile(e) { form.file = e.target.files[0]; }
function submit() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/import`, { forceFormData: true });
}
</script>
