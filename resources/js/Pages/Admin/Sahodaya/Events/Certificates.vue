<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificates`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificates`" eyebrow="Operations"
                    description="Generate and manage participant certificates." />
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded p-1">
                <select v-if="publishedItems.length" v-model="selectedItemId" class="text-xs py-1.5 px-2.5 rounded border-gray-300 bg-white shadow-sm focus:ring-1 focus:ring-indigo-500 max-w-[240px] truncate">
                    <option :value="null">All items</option>
                    <option v-for="item in publishedItems" :key="item.id" :value="item.id">
                        {{ item.item_code ? `[${item.item_code}] ` : '' }}{{ item.title }}
                    </option>
                </select>
                <button @click="generate(selectedItemId)" class="btn-primary py-1.5 px-3 text-xs shrink-0">
                    🏆 Generate top 3 {{ selectedItemId ? 'for selected item' : '' }}
                </button>
            </div>
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

        <!-- View mode tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
                <button @click="activeTab = 'winners_item'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'winners_item'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    🏆 Winners (Grouped by Item)
                </button>
                <button @click="activeTab = 'winners_school'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'winners_school'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    🏫 Winners (Grouped by School)
                </button>
                <button @click="activeTab = 'all'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'all'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    📜 All Certificates ({{ certificates.length }})
                </button>
            </nav>
        </div>

        <!-- TAB 1: Winners Grouped by Item -->
        <div v-if="activeTab === 'winners_item'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Winners Grouped by Item</h3>
                    <p class="text-xs text-gray-500">Items whose results have been published (Ranks 1–3).</p>
                </div>
            </div>

            <div v-if="winnersByItem.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="group in winnersByItem" :key="group.item_id" class="card p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <p class="font-semibold text-sm text-gray-900 leading-tight">{{ group.item_title }}</p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">
                                {{ group.winners.length }} winner{{ group.winners.length === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <ul class="space-y-2 mb-4">
                            <li v-for="w in group.winners" :key="w.id" class="flex items-center justify-between gap-2 text-xs">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="shrink-0 rounded-full bg-amber-500 text-white font-bold w-5 h-5 flex items-center justify-center text-[10px]">
                                        {{ w.position ?? '—' }}
                                    </span>
                                    <span class="truncate font-medium text-gray-800">{{ w.name }}</span>
                                </span>
                                <span class="flex items-center gap-1.5 shrink-0">
                                    <a :href="`/certificates/print/${w.uuid}${plainMode ? '?plain=1' : ''}`" target="_blank" class="text-indigo-600 font-medium hover:underline">Print ↗</a>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                        <button @click="generate(group.item_id)"
                                class="text-xs font-semibold text-amber-700 hover:text-amber-900 flex items-center gap-1">
                            ⚡ Generate Certs
                        </button>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?item_id=${group.item_id}&cert_type=winner${plainMode ? '&plain=1' : ''}`"
                           target="_blank"
                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            🖨️ Print ↗
                        </a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/download-zip?item_id=${group.item_id}&cert_type=winner${plainMode ? '&plain=1' : ''}`"
                           class="text-xs font-semibold text-gray-600 hover:text-gray-800 flex items-center gap-1">
                            📦 ZIP
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                No published winners by item yet. Publish item results to generate winner certificates.
            </div>
        </div>

        <!-- TAB 2: Winners Grouped by School -->
        <div v-if="activeTab === 'winners_school'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Winners Grouped by School</h3>
                    <p class="text-xs text-gray-500">Winners organized by school for distribution.</p>
                </div>
            </div>

            <div v-if="winnersBySchool.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="group in winnersBySchool" :key="group.school_id" class="card p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <p class="font-semibold text-sm text-gray-900 leading-tight">{{ group.school_name }}</p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-medium">
                                {{ group.winners.length }} winner{{ group.winners.length === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <ul class="space-y-2 mb-4">
                            <li v-for="w in group.winners" :key="w.id" class="flex items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800 flex items-center gap-1.5">
                                        <span class="shrink-0 rounded-full bg-amber-500 text-white font-bold w-4 h-4 flex items-center justify-center text-[9px]">
                                            {{ w.position ?? '—' }}
                                        </span>
                                        <span>{{ w.name }}</span>
                                    </p>
                                    <p class="text-[11px] text-gray-500 truncate">{{ w.item_title }}</p>
                                </div>
                                <a :href="`/certificates/print/${w.uuid}${plainMode ? '?plain=1' : ''}`" target="_blank" class="text-indigo-600 font-medium shrink-0 hover:underline">Print ↗</a>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?school_id=${group.school_id}&cert_type=winner${plainMode ? '&plain=1' : ''}`"
                           target="_blank"
                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            🖨️ Print School ↗
                        </a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/download-zip?school_id=${group.school_id}&cert_type=winner${plainMode ? '&plain=1' : ''}`"
                           class="text-xs font-semibold text-gray-600 hover:text-gray-800 flex items-center gap-1">
                            📦 Download ZIP
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                No winners grouped by school available yet.
            </div>
        </div>

        <!-- TAB 3: All Certificates -->
        <div v-if="activeTab === 'all'" class="card p-4">
            <div v-if="certificates.length" class="divide-y divide-gray-100">
                <div v-for="c in certificates" :key="c.id" class="py-3 flex items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-sm text-gray-900">{{ c.student?.name ?? c.participant?.student?.name ?? 'Participant' }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider"
                                  :class="c.cert_type === 'winner' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'">
                                {{ certificateTypeLabel(c.cert_type) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ c.item?.title ?? 'Event Participant' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <a :href="`/certificates/verify/${c.uuid}`" target="_blank" class="text-gray-500 hover:text-gray-700">Verify ↗</a>
                        <a :href="`/certificates/print/${c.uuid}?preview=1`" target="_blank" class="text-gray-500 hover:text-gray-700">Preview ↗</a>
                        <a :href="`/certificates/print/${c.uuid}${plainMode ? '?plain=1' : ''}`" target="_blank" class="font-semibold text-indigo-600 hover:underline">Print ↗</a>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-gray-500 text-sm py-8">
                No certificates generated yet. Click "Generate for top 3" or "Generate participation certificates" above.
            </div>
        </div>

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
    publishedItems: { type: Array, default: () => [] },
    winnersByItem: { type: Array, default: () => [] },
    winnersBySchool: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const activeTab = ref('winners_item');
const plainMode = ref(false);
const selectedItemId = ref(null);

const downloadZipUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/download-zip${plainMode.value ? '?plain=1' : ''}`);
const downloadPublishedZipUrl = computed(() => {
    const params = new URLSearchParams({ published_only: '1' });
    if (plainMode.value) params.set('plain', '1');
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/download-zip?${params}`;
});
const printAllUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/print-all${plainMode.value ? '?plain=1' : ''}`);

function generate(itemId = null) {
    const targetId = itemId || selectedItemId.value;
    const payload = targetId ? { item_id: targetId } : {};
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/generate`, payload, { preserveScroll: true });
}

function generateParticipation() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/participation`, {}, { preserveScroll: true });
}

function certificateTypeLabel(type) {
    return type === 'winner' ? 'Winner' : 'Participation';
}
</script>
