<template>
    <SahodayaEventsLayout :title="`${event.title} — Participation Certificates`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Participation Certificates`" eyebrow="Operations"
                    description="One certificate per person for the whole event, filterable by item or school." />

        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates`" class="text-gray-500 hover:text-gray-800">
                ← All certificates
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/merit`" class="text-gray-500 hover:text-gray-800">
                Merit certificates →
            </Link>
        </div>

        <!-- Filter + pipeline: item/school here drive both the list below and every
             bulk action (render/download/preview). Generation itself isn't item-scoped
             (a participation certificate is issued once per person for the whole event,
             see FestCertificateService::generateParticipationForEvent()) — the item
             filter here narrows to people who registered for that item, not a
             per-item generate action. -->
        <div class="card !p-4 mb-6">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-indigo-700 mb-2">Scope</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <select v-model="selectedItemId" class="text-xs py-1.5 px-2.5 rounded border-gray-300 bg-white shadow-sm focus:ring-1 focus:ring-indigo-500 max-w-[220px] truncate">
                            <option :value="null">All items</option>
                            <option v-for="item in publishedItems" :key="item.id" :value="item.id">
                                {{ item.item_code ? `[${item.item_code}] ` : '' }}{{ item.title }}
                            </option>
                        </select>
                        <select v-model="selectedSchoolId" class="text-xs py-1.5 px-2.5 rounded border-gray-300 bg-white shadow-sm focus:ring-1 focus:ring-indigo-500 max-w-[220px] truncate">
                            <option :value="null">All schools ({{ schools.length }})</option>
                            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <button @click="generate" class="btn-secondary py-1.5 px-3 text-xs">⚡ Generate all</button>
                    </div>
                </div>

                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-indigo-700 mb-2">
                        Bulk actions — {{ scopeLabel }} ({{ filteredCertificates.length }})
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button @click="renderFiltered" class="btn-primary py-1.5 px-3 text-xs" :disabled="isBatchRunning || !filteredCertificates.length">
                            ⚙️ Render matching
                        </button>
                        <a :href="previewFilteredUrl" target="_blank" class="text-xs font-semibold text-gray-500 hover:text-gray-800">👁️ Preview ↗</a>
                        <details v-if="filteredCertificates.length" class="relative">
                            <summary class="btn-secondary py-1.5 px-3 text-xs inline-flex list-none cursor-pointer [&::-webkit-details-marker]:hidden">
                                📦 Download / print ▾
                            </summary>
                            <div class="absolute z-20 mt-1 w-64 rounded-lg border border-gray-200 bg-white shadow-lg p-1">
                                <a :href="downloadFilteredUrl" class="block px-3 py-2 text-xs rounded hover:bg-gray-50">📦 Matching certificates (ZIP)</a>
                                <a :href="printFilteredUrl(false)" target="_blank" class="block px-3 py-2 text-xs rounded hover:bg-gray-50">🖨️ Print matching (with background) ↗</a>
                                <a :href="printFilteredUrl(true)" target="_blank" class="block px-3 py-2 text-xs rounded hover:bg-gray-50">🖨️ Print matching (plain) ↗</a>
                            </div>
                        </details>
                        <button v-if="staleCount" @click="regenerateStale" class="text-xs font-semibold !text-amber-700" :disabled="isBatchRunning">
                            🔁 {{ staleCount }} stale
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="jobStatus" class="mt-4 pt-4 border-t border-gray-100 text-sm">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <p class="font-semibold capitalize">
                        {{ jobStatus.batch_type === 'regenerate_stale' ? 'Regenerating stale certificates' : 'Rendering certificates' }}: {{ jobStatus.status.replace('_', ' ') }}
                    </p>
                    <span class="text-xs text-gray-500 tabular-nums">{{ jobStatus.processed_count }} / {{ jobStatus.total_count }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all"
                         :style="{ width: (jobStatus.total_count ? (jobStatus.processed_count / jobStatus.total_count * 100) : 0) + '%' }"></div>
                </div>
                <p v-if="['completed', 'completed_with_errors'].includes(jobStatus.status)" class="mt-2 text-xs text-gray-600">
                    {{ jobStatus.succeeded_count }} succeeded<span v-if="jobStatus.failed_count"> · {{ jobStatus.failed_count }} failed</span>
                </p>
                <p v-if="jobStatus.error" class="mt-2 text-xs text-red-600">{{ jobStatus.error }}</p>
            </div>
        </div>

        <!-- Bulk Action Toolbar for ad-hoc checkbox selection -->
        <div v-if="selectedCertIds.length" class="bg-indigo-50 border border-indigo-200 rounded p-3 flex flex-wrap items-center justify-between gap-3 text-xs mb-4">
            <span class="font-semibold text-indigo-900">
                Selected {{ selectedCertIds.length }} certificate{{ selectedCertIds.length === 1 ? '' : 's' }}
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="bulkPrint(false)" class="btn-primary py-1 px-3 text-xs">🖨️ Bulk Print (With BG)</button>
                <button @click="bulkPrint(true)" class="btn-secondary py-1 px-3 text-xs">📄 Bulk Print Plain</button>
                <button @click="bulkDownload" class="btn-secondary py-1 px-3 text-xs">📦 Bulk Download ZIP</button>
                <button @click="selectedCertIds = []" class="text-gray-500 hover:text-gray-700 ml-2">Clear</button>
            </div>
        </div>

        <div class="card p-4">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[200px] flex-1">
                    <input v-model="searchQuery" type="text" placeholder="Search student or item..."
                           class="w-full text-xs py-2 pl-8 pr-3 rounded border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <span class="absolute left-2.5 top-2 text-gray-400 text-xs">🔍</span>
                </div>
                <select v-model="perPage" class="text-xs py-1.5 px-2 rounded border-gray-300 shrink-0">
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                    <option value="all">All</option>
                </select>
            </div>

            <div v-if="paginatedCertificates.length" class="divide-y divide-gray-100">
                <div class="py-2 px-1 flex items-center justify-between text-xs font-semibold text-gray-500 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" :checked="isAllSelectedOnPage" @change="toggleSelectAllPage" class="rounded border-gray-300">
                        <span>Student / Item / School</span>
                    </div>
                    <span>Actions</span>
                </div>

                <div v-for="c in paginatedCertificates" :key="c.id" class="py-3 flex items-center justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <input type="checkbox" :value="c.id" v-model="selectedCertIds" class="mt-1 rounded border-gray-300">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-gray-900">{{ c.student?.name ?? c.participant?.student?.name ?? 'Participant' }}</span>
                                <span v-if="c.is_stale" class="text-[10px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider bg-amber-100 text-amber-800">⚠️ Stale</span>
                                <span v-else-if="!c.is_rendered" class="text-[10px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider bg-gray-100 text-gray-500">Not rendered</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-0.5 truncate">
                                {{ c.item?.title ?? 'Event Participant' }}<span class="text-gray-400"> (+ any other items entered)</span>
                                <span class="text-gray-400"> · </span>
                                <span class="text-gray-500 font-medium">{{ c.registration?.school?.name ?? c.participant?.registration?.school?.name }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs shrink-0">
                        <a :href="`/certificates/print/${c.uuid}?preview=1`" target="_blank" class="text-gray-500 hover:text-gray-700">Preview ↗</a>
                        <a :href="`/certificates/print/${c.uuid}`" target="_blank" class="font-semibold text-indigo-600 hover:underline">Print ↗</a>
                        <a :href="`/certificates/print/${c.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Plain ↗</a>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-gray-500 text-sm py-8">No participation certificates match this scope yet.</div>

            <div v-if="totalPages > 1" class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                <span>Showing {{ ((currentPage - 1) * perPageNum) + 1 }} to {{ Math.min(currentPage * perPageNum, filteredCertificates.length) }} of {{ filteredCertificates.length }}</span>
                <div class="flex items-center gap-1">
                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-2.5 py-1 rounded border border-gray-300 disabled:opacity-40">Previous</button>
                    <span class="px-2 font-medium">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-2.5 py-1 rounded border border-gray-300 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, certificates: Array,
    publishedItems: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    recentBatches: { type: Array, default: () => [] },
    staleCount: { type: Number, default: 0 },
    activityLogs: { type: Array, default: () => [] },
});

const page = usePage();
const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates`;

const searchQuery = ref('');
const selectedItemId = ref(null);
const selectedSchoolId = ref(null);
const perPage = ref(25);
const currentPage = ref(1);
const selectedCertIds = ref([]);
const jobStatus = ref(null);
let pollTimer = null;

const filteredCertificates = computed(() => props.certificates.filter(c => {
    if (selectedItemId.value && c.item?.id !== selectedItemId.value) return false;
    if (selectedSchoolId.value) {
        const schoolId = c.registration?.school?.id ?? c.participant?.registration?.school?.id;
        if (schoolId !== selectedSchoolId.value) return false;
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        const studentName = (c.student?.name ?? c.participant?.student?.name ?? '').toLowerCase();
        const itemTitle = (c.item?.title ?? '').toLowerCase();
        if (!studentName.includes(q) && !itemTitle.includes(q)) return false;
    }
    return true;
}));

const scopeLabel = computed(() => {
    if (selectedItemId.value) return props.publishedItems.find(i => i.id === selectedItemId.value)?.title ?? 'Item';
    if (selectedSchoolId.value) return props.schools.find(s => s.id === selectedSchoolId.value)?.name ?? 'School';
    return 'Whole event';
});

const perPageNum = computed(() => perPage.value === 'all' ? filteredCertificates.value.length || 1 : Number(perPage.value));
const totalPages = computed(() => Math.ceil(filteredCertificates.value.length / perPageNum.value) || 1);
const paginatedCertificates = computed(() => {
    if (perPage.value === 'all') return filteredCertificates.value;
    const start = (currentPage.value - 1) * perPageNum.value;
    return filteredCertificates.value.slice(start, start + perPageNum.value);
});

const isAllSelectedOnPage = computed(() => paginatedCertificates.value.length > 0 && paginatedCertificates.value.every(c => selectedCertIds.value.includes(c.id)));
function toggleSelectAllPage() {
    if (isAllSelectedOnPage.value) {
        const pageIds = new Set(paginatedCertificates.value.map(c => c.id));
        selectedCertIds.value = selectedCertIds.value.filter(id => !pageIds.has(id));
    } else {
        const set = new Set(selectedCertIds.value);
        paginatedCertificates.value.forEach(c => set.add(c.id));
        selectedCertIds.value = Array.from(set);
    }
}

const isBatchRunning = computed(() => jobStatus.value && !['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(jobStatus.value.status));

function scopeParams() {
    const params = new URLSearchParams({ cert_type: 'participation' });
    if (selectedItemId.value) params.set('item_id', selectedItemId.value);
    if (selectedSchoolId.value) params.set('school_id', selectedSchoolId.value);
    return params;
}

const downloadFilteredUrl = computed(() => `${base}/download-zip?${scopeParams()}`);
function printFilteredUrl(plain) {
    const params = scopeParams();
    if (plain) params.set('plain', '1');
    return `${base}/print-all?${params}`;
}
const previewFilteredUrl = computed(() => `${base}/preview-sample?${scopeParams()}`);

function startPolling(batchId) {
    if (!batchId) return;
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
        const res = await fetch(`${base}/batches/${batchId}/progress`, { headers: { Accept: 'application/json' } });
        jobStatus.value = await res.json();
        if (['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(jobStatus.value?.status)) {
            clearInterval(pollTimer);
            if (['completed', 'completed_with_errors'].includes(jobStatus.value.status)) {
                router.reload({ only: ['certificates', 'recentBatches', 'staleCount'] });
            }
        }
    }, 3000);
}

function renderFiltered() {
    const scope = { cert_type: 'participation' };
    if (selectedItemId.value) scope.item_id = selectedItemId.value;
    if (selectedSchoolId.value) scope.school_id = selectedSchoolId.value;
    router.post(`${base}/batches`, scope, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
    });
}

function regenerateStale() {
    router.post(`${base}/regenerate-stale`, {}, {
        preserveScroll: true,
        onSuccess: () => startPolling(page.props.flash?.certificate_batch_id),
    });
}

function generate() {
    router.post(`${base}/participation`, {}, { preserveScroll: true });
}

function bulkPrint(plain = false) {
    if (!selectedCertIds.value.length) return;
    window.open(`${base}/print-all?certificate_ids=${selectedCertIds.value.join(',')}${plain ? '&plain=1' : ''}`, '_blank');
}

function bulkDownload() {
    if (!selectedCertIds.value.length) return;
    window.location.href = `${base}/download-zip?certificate_ids=${selectedCertIds.value.join(',')}`;
}

onMounted(() => {
    const lastBatch = props.recentBatches?.[0];
    if (lastBatch && !['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(lastBatch.status)) {
        startPolling(lastBatch.id);
    }
});
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });
</script>
