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
                    🏆 Generate Merit Certificates {{ selectedItemId ? 'for item' : '' }}
                </button>
            </div>

            <button @click="generateParticipation" class="btn-secondary">Generate participation certificates</button>
            <a v-if="certificates.length" :href="downloadZipUrl" class="btn-secondary">Download all (ZIP)</a>
            <a v-if="winnersByItem.length" :href="downloadPublishedZipUrl" class="btn-secondary">Download merit winners (ZIP)</a>
            <a v-if="certificates.length" :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all`" target="_blank" class="btn-secondary">Print all (With BG) ↗</a>
            <a v-if="certificates.length" :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?plain=1`" target="_blank" class="btn-secondary">Print all plain (No BG) ↗</a>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/tally`" class="btn-secondary">
                How many do I need to print?
            </Link>
        </div>

        <!-- View mode tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
                <button @click="activeTab = 'winners_item'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'winners_item'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    🏆 Merit Winners (Grouped by Item)
                </button>
                <button @click="activeTab = 'winners_school'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors"
                        :class="activeTab === 'winners_school'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    🏫 Merit Winners (Grouped by School)
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

        <!-- TAB 1: Merit Winners Grouped by Item -->
        <div v-if="activeTab === 'winners_item'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Merit Winners Grouped by Item</h3>
                    <p class="text-xs text-gray-500">Items whose results have been published (Merit Ranks 1–3).</p>
                </div>
            </div>

            <div v-if="winnersByItem.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="group in winnersByItem" :key="group.item_id" class="card p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <p class="font-semibold text-sm text-gray-900 leading-tight">{{ group.item_title }}</p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">
                                {{ group.winners.length }} merit winner{{ group.winners.length === 1 ? '' : 's' }}
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
                                <span class="flex items-center gap-2 shrink-0 text-[11px]">
                                    <a :href="`/certificates/print/${w.uuid}`" target="_blank" class="text-indigo-600 font-medium hover:underline">Print (With BG) ↗</a>
                                    <a :href="`/certificates/print/${w.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Plain ↗</a>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <button @click="generate(group.item_id)"
                                class="font-semibold text-amber-700 hover:text-amber-900 flex items-center gap-1">
                            ⚡ Generate Merit
                        </button>
                        <div class="flex items-center gap-2">
                            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?item_id=${group.item_id}&cert_type=winner`"
                               target="_blank"
                               class="font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                🖨️ Print Item ↗
                            </a>
                            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?item_id=${group.item_id}&cert_type=winner&plain=1`"
                               target="_blank"
                               class="font-medium text-gray-500 hover:text-gray-800">
                                Plain (No BG) ↗
                            </a>
                            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/download-zip?item_id=${group.item_id}&cert_type=winner`"
                               class="font-semibold text-gray-600 hover:text-gray-800 flex items-center gap-1">
                                📦 ZIP
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                No published merit winners by item yet. Publish item results to generate merit certificates.
            </div>
        </div>

        <!-- TAB 2: Merit Winners Grouped by School -->
        <div v-if="activeTab === 'winners_school'" class="mb-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Merit Winners Grouped by School</h3>
                    <p class="text-xs text-gray-500">Merit winners organized by school for distribution.</p>
                </div>
            </div>

            <div v-if="winnersBySchool.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="group in winnersBySchool" :key="group.school_id" class="card p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <p class="font-semibold text-sm text-gray-900 leading-tight">{{ group.school_name }}</p>
                            <span class="shrink-0 text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-medium">
                                {{ group.winners.length }} merit winner{{ group.winners.length === 1 ? '' : 's' }}
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
                                <div class="flex items-center gap-2 shrink-0 text-[11px]">
                                    <a :href="`/certificates/print/${w.uuid}`" target="_blank" class="text-indigo-600 font-medium hover:underline">Print (With BG) ↗</a>
                                    <a :href="`/certificates/print/${w.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Plain ↗</a>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?school_id=${group.school_id}&cert_type=winner`"
                           target="_blank"
                           class="font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            🖨️ Print School (With BG) ↗
                        </a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/print-all?school_id=${group.school_id}&cert_type=winner&plain=1`"
                           target="_blank"
                           class="font-medium text-gray-500 hover:text-gray-800">
                            Print Plain (No BG) ↗
                        </a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/download-zip?school_id=${group.school_id}&cert_type=winner`"
                           class="font-semibold text-gray-600 hover:text-gray-800 flex items-center gap-1">
                            📦 ZIP
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="card p-6 text-center text-gray-500 text-sm">
                No merit winners grouped by school available yet.
            </div>
        </div>

        <!-- TAB 3: All Certificates with School Filter, Search, Pagination, & Bulk Actions -->
        <div v-if="activeTab === 'all'" class="space-y-4">
            <!-- Filter & Search Controls Bar -->
            <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[280px]">
                    <!-- Search Input -->
                    <div class="relative min-w-[200px] flex-1">
                        <input v-model="searchQuery" type="text" placeholder="Search student or item..."
                               class="w-full text-xs py-2 pl-8 pr-3 rounded border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <span class="absolute left-2.5 top-2 text-gray-400 text-xs">🔍</span>
                    </div>

                    <!-- School Filter -->
                    <select v-model="selectedSchoolId" class="text-xs py-2 px-3 rounded border-gray-300 shadow-sm focus:ring-indigo-500 max-w-[240px] truncate">
                        <option :value="null">All Schools ({{ schools.length }})</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>

                    <!-- Certificate Type Filter -->
                    <select v-model="selectedCertType" class="text-xs py-2 px-3 rounded border-gray-300 shadow-sm focus:ring-indigo-500">
                        <option :value="null">All Types</option>
                        <option value="winner">Merit Winners</option>
                        <option value="participation">Participation</option>
                    </select>
                </div>

                <!-- Page Size & Download School ZIP -->
                <div class="flex items-center gap-3 text-xs text-gray-600 shrink-0">
                    <a v-if="selectedSchoolId"
                       :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/download-zip?school_id=${selectedSchoolId}`"
                       class="btn-secondary py-1.5 px-3 text-xs flex items-center gap-1">
                        📦 Download School ZIP
                    </a>
                    <div class="flex items-center gap-1.5">
                        <span>Show:</span>
                        <select v-model="perPage" class="text-xs py-1.5 px-2 rounded border-gray-300">
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bulk Action Toolbar (when checkboxes are selected) -->
            <div v-if="selectedCertIds.length" class="bg-indigo-50 border border-indigo-200 rounded p-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                <span class="font-semibold text-indigo-900">
                    Selected {{ selectedCertIds.length }} certificate{{ selectedCertIds.length === 1 ? '' : 's' }}
                </span>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="bulkPrint(false)" class="btn-primary py-1 px-3 text-xs">
                        🖨️ Bulk Print (With BG)
                    </button>
                    <button @click="bulkPrint(true)" class="btn-secondary py-1 px-3 text-xs">
                        📄 Bulk Print Plain (No BG)
                    </button>
                    <button @click="bulkDownload" class="btn-secondary py-1 px-3 text-xs">
                        📦 Bulk Download ZIP
                    </button>
                    <button @click="selectedCertIds = []" class="text-gray-500 hover:text-gray-700 ml-2">
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Certificate Table / List -->
            <div class="card p-4">
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
                                    <span class="text-[10px] px-2 py-0.5 rounded font-semibold uppercase tracking-wider"
                                          :class="c.cert_type === 'winner' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'">
                                        {{ certificateTypeLabel(c.cert_type) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5 truncate">
                                    {{ c.item?.title ?? 'Event Participant' }}
                                    <span v-if="c.registration?.school?.name || c.participant?.registration?.school?.name" class="text-gray-400"> · </span>
                                    <span class="text-gray-500 font-medium">{{ c.registration?.school?.name ?? c.participant?.registration?.school?.name }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs shrink-0">
                            <a :href="`/certificates/verify/${c.uuid}`" target="_blank" class="text-gray-500 hover:text-gray-700">Verify ↗</a>
                            <a :href="`/certificates/print/${c.uuid}?preview=1`" target="_blank" class="text-gray-500 hover:text-gray-700">Preview ↗</a>
                            <a :href="`/certificates/print/${c.uuid}`" target="_blank" class="font-semibold text-indigo-600 hover:underline">Print (With BG) ↗</a>
                            <a :href="`/certificates/print/${c.uuid}?plain=1`" target="_blank" class="text-gray-500 hover:underline">Print Plain ↗</a>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center text-gray-500 text-sm py-8">
                    No matching certificates found.
                </div>

                <!-- Pagination Footer -->
                <div v-if="totalPages > 1" class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                    <span>
                        Showing {{ ((currentPage - 1) * perPageNum) + 1 }} to {{ Math.min(currentPage * perPageNum, filteredCertificates.length) }} of {{ filteredCertificates.length }} certificates
                    </span>
                    <div class="flex items-center gap-1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                                class="px-2.5 py-1 rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                            Previous
                        </button>
                        <span class="px-2 font-medium">Page {{ currentPage }} of {{ totalPages }}</span>
                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                                class="px-2.5 py-1 rounded border border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, certificates: Array,
    publishedItems: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    winnersByItem: { type: Array, default: () => [] },
    winnersBySchool: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const activeTab = ref('winners_item');
const plainMode = ref(false);
const selectedItemId = ref(null);

// Search, Filter, & Pagination state
const searchQuery = ref('');
const selectedSchoolId = ref(null);
const selectedCertType = ref(null);
const perPage = ref(25);
const currentPage = ref(1);
const selectedCertIds = ref([]);

const filteredCertificates = computed(() => {
    return props.certificates.filter(c => {
        if (selectedCertType.value && c.cert_type !== selectedCertType.value) {
            return false;
        }
        if (selectedSchoolId.value) {
            const schoolId = c.registration?.school?.id ?? c.participant?.registration?.school?.id;
            if (schoolId !== selectedSchoolId.value) return false;
        }
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim();
            const studentName = (c.student?.name ?? c.participant?.student?.name ?? '').toLowerCase();
            const itemTitle = (c.item?.title ?? '').toLowerCase();
            const schoolName = (c.registration?.school?.name ?? c.participant?.registration?.school?.name ?? '').toLowerCase();
            if (!studentName.includes(q) && !itemTitle.includes(q) && !schoolName.includes(q)) {
                return false;
            }
        }
        return true;
    });
});

const perPageNum = computed(() => perPage.value === 'all' ? filteredCertificates.value.length || 1 : Number(perPage.value));

const totalPages = computed(() => Math.ceil(filteredCertificates.value.length / perPageNum.value) || 1);

const paginatedCertificates = computed(() => {
    if (perPage.value === 'all') return filteredCertificates.value;
    const start = (currentPage.value - 1) * perPageNum.value;
    return filteredCertificates.value.slice(start, start + perPageNum.value);
});

watch([searchQuery, selectedSchoolId, selectedCertType, perPage], () => {
    currentPage.value = 1;
});

const isAllSelectedOnPage = computed(() => {
    if (!paginatedCertificates.value.length) return false;
    return paginatedCertificates.value.every(c => selectedCertIds.value.includes(c.id));
});

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

function bulkPrint(plain = false) {
    if (!selectedCertIds.value.length) return;
    const ids = selectedCertIds.value.join(',');
    const url = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/print-all?certificate_ids=${ids}${plain ? '&plain=1' : ''}`;
    window.open(url, '_blank');
}

function bulkDownload() {
    if (!selectedCertIds.value.length) return;
    const ids = selectedCertIds.value.join(',');
    window.location.href = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/download-zip?certificate_ids=${ids}`;
}

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
