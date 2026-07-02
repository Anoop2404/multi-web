<template>
    <SahodayaEventsLayout :title="`${event.title} — Import`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader :title="`${event.title} — Import registrations`" eyebrow="Registrations"
                    description="Bulk import cluster registrations from CSV." />

        <div class="card max-w-xl space-y-4">
            <p class="text-sm text-slate-600">Each row needs school_id or school_prefix, item reference, and participant details.</p>
            <div class="flex flex-wrap gap-3 items-center">
                <a :href="`${base}/registrations/import-template`" class="text-sm font-semibold text-[color:var(--brand-blue)]">Download template</a>
            </div>
            <form @submit.prevent="submitImport" class="space-y-3">
                <input type="file" accept=".csv" class="field text-sm" @change="onFile">
                <button type="submit" class="btn-primary" :disabled="!importFile || importForm.processing">Import CSV</button>
            </form>
            <Link :href="`${base}/registrations`" class="text-sm link-brand">← Back to registrations</Link>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8 max-w-xl" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const importFile = ref(null);
const importForm = useForm({ file: null });

function onFile(e) {
    importFile.value = e.target.files?.[0] ?? null;
}
function submitImport() {
    if (!importFile.value) return;
    importForm.transform(() => ({ file: importFile.value })).post(`${base}/registrations/import`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { importFile.value = null; },
    });
}
</script>
