<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificates`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificates`" eyebrow="Operations"
                    description="Generate and manage participant certificates." />
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button @click="generate" class="btn-primary">Generate for top 3</button>
            <button @click="generateParticipation" class="btn-secondary">Generate participation certificates</button>
            <a v-if="certificates.length" :href="downloadZipUrl" class="btn-secondary">Download all (ZIP)</a>
            <a v-if="winnersByItem.length" :href="downloadPublishedZipUrl" class="btn-secondary">Download published winners (ZIP)</a>
            <a v-if="certificates.length" :href="printAllUrl" target="_blank" class="btn-secondary">Print all ↗</a>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/tally`" class="btn-secondary">
                How many do I need to print?
            </Link>
            <label v-if="certificates.length" class="flex items-center gap-1.5 text-xs text-gray-600 ml-1">
                <input type="checkbox" v-model="plainMode" class="rounded border-gray-300">
                Plain (no background — saves ink)
            </label>
        </div>

        <div v-if="winnersByItem.length" class="mb-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Winners by item</h3>
            <p class="text-xs text-gray-500 mb-3">Items whose results have been published, rank 1–3.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="group in winnersByItem" :key="group.item_id" class="card p-3">
                    <p class="font-medium text-sm mb-2">{{ group.item_title }}</p>
                    <ul class="space-y-1.5">
                        <li v-for="w in group.winners" :key="w.id" class="flex items-center justify-between gap-2 text-xs">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <span class="shrink-0 rounded-full bg-amber-100 text-amber-800 font-semibold w-5 h-5 flex items-center justify-center text-[10px]">
                                    {{ w.position ?? '—' }}
                                </span>
                                <span class="truncate">{{ w.name }}</span>
                            </span>
                            <span class="flex items-center gap-2 shrink-0">
                                <a :href="`/certificates/verify/${w.uuid}`" target="_blank" class="text-indigo-600 font-medium">Verify ↗</a>
                                <a :href="`/certificates/print/${w.uuid}${plainMode ? '?plain=1' : ''}`" target="_blank" class="text-gray-600 font-medium">Print ↗</a>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <ul class="card-list">
            <li v-for="c in certificates" :key="c.id" class="p-4 flex flex-wrap gap-2 justify-between items-center text-sm">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium">{{ c.student?.name ?? 'Participant' }}</p>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                              :class="c.cert_type === 'winner'
                                  ? 'bg-amber-100 text-amber-800'
                                  : 'bg-sky-100 text-sky-800'">
                            {{ certificateTypeLabel(c.cert_type) }}
                        </span>
                    </div>
                    <p class="text-gray-500 text-xs">
                        {{ c.item?.title }}
                        <template v-if="c.cert_type === 'winner'"> · Position {{ c.mark?.position ?? '—' }}</template>
                    </p>
                </div>
                <a :href="`/certificates/verify/${c.uuid}`" target="_blank" class="text-indigo-600 text-xs font-medium mr-3">Verify ↗</a>
                <a :href="`/certificates/print/${c.uuid}?preview=1${plainMode ? '&plain=1' : ''}`" target="_blank" class="text-gray-600 text-xs font-medium mr-3">Preview ↗</a>
                <a :href="`/certificates/print/${c.uuid}${plainMode ? '?plain=1' : ''}`" target="_blank" class="text-gray-600 text-xs font-medium">Print ↗</a>
            </li>
            <li v-if="!certificates.length" class="p-4 text-gray-400 text-sm">No certificates yet. Publish results or click Generate.</li>
        </ul>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, certificates: Array,
    winnersByItem: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const plainMode = ref(false);

const downloadZipUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/download-zip${plainMode.value ? '?plain=1' : ''}`);
const downloadPublishedZipUrl = computed(() => {
    const params = new URLSearchParams({ published_only: '1' });
    if (plainMode.value) params.set('plain', '1');
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/download-zip?${params}`;
});
const printAllUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/print-all${plainMode.value ? '?plain=1' : ''}`);

function generate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/generate`, {}, { preserveScroll: true });
}

function generateParticipation() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/participation`, {}, { preserveScroll: true });
}

function certificateTypeLabel(type) {
    return type === 'winner' ? 'Winner' : 'Participation';
}
</script>
