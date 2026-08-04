<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results"
                    description="Review CBSE board results submitted by member schools — verify, approve, reject, or publish.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm">Masters</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-sm">Reports</Link>
            </template>
        </PageHeader>

        <p v-if="selectedClass" class="text-sm -mt-2 mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification?class=${selectedClass === 12 ? 10 : 12}&status=${filters.status}`" class="text-indigo-600 hover:underline font-medium">
                Switch to {{ selectedClass === 12 ? 'Class X' : 'Class XII' }} →
            </Link>
        </p>

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Queue Size</p>
                <p class="text-2xl font-bold text-[#0f3d7a] mt-1">{{ results.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Results awaiting action in the selected view</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Submitted</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ statusCounts.submitted }}</p>
                <p class="text-xs text-slate-500 mt-1">Need review before verification</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Verified / Approved</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ statusCounts.verified + statusCounts.approved }}</p>
                <p class="text-xs text-slate-500 mt-1">Ready for publish or already approved</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">With Toppers</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ topperCount }}</p>
                <p class="text-xs text-slate-500 mt-1">Submissions containing topper data</p>
            </div>
        </div>

        <div class="card !p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Verification queue</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Review school-submitted results, open the latest proof PDF, then verify, approve, or reject.
                </p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-[#0f3d7a]">{{ results.total }}</p>
                <p class="text-[11px] uppercase tracking-wide text-slate-400">matching results</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="(label, value) in statusOptions" :key="value"
                  :href="statusHref(value)"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === value ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                {{ label }}
            </Link>
        </div>

        <div class="card !p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Sahodaya-wide toppers</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Default cap: {{ defaultTopN }} per school submission. The auto-computed Sahodaya-wide list
                    (Top-N + tie handling) now lives on its own page.
                </p>
            </div>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers`" class="btn-secondary text-sm shrink-0">
                Sahodaya Toppers →
            </Link>
        </div>

        <div class="space-y-3">
            <div v-for="r in results.data" :key="r.id" class="card !p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-[#0f3d7a]">{{ schoolNames[r.tenant_id] || r.tenant_id }}</p>
                        <p class="text-sm text-slate-700 mt-1">
                            Class {{ r.class }} · {{ r.examination_type }} · {{ r.academic_year }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            <span class="capitalize">{{ r.status }}</span>
                            · {{ r.pass_percent }}% pass
                            · {{ r.total_appeared }} appeared
                            <span v-if="r.highest_mark"> · high {{ r.highest_mark }}</span>
                            <span v-if="r.toppers?.length"> · {{ r.toppers.length }} toppers</span>
                        </p>
                        <p v-if="r.latest_proof_label" class="text-xs text-slate-500 mt-1">
                            Latest proof:
                            <span class="font-medium text-slate-700">{{ r.latest_proof_label }}</span>
                            <span v-if="r.latest_proof_type" class="ml-1 text-slate-400">
                                ({{ proofTypeLabel(r.latest_proof_type) }})
                            </span>
                        </p>
                        <p v-if="r.rejection_reason" class="text-xs text-red-600 mt-1">{{ r.rejection_reason }}</p>
                        <p v-if="r.uploads?.length" class="text-xs text-slate-500 mt-1 flex flex-wrap gap-2 items-center">
                            <span class="text-slate-400">Uploaded versions:</span>
                            <button v-for="u in r.uploads" :key="u.id"
                                    type="button"
                                    @click="openVersionPreview(r, u)"
                                    class="underline text-indigo-700 hover:text-indigo-900">
                                v{{ u.version }}{{ u.file_name ? ` · ${u.file_name}` : '' }}
                            </button>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <button v-if="r.latest_proof_url"
                                type="button"
                                class="px-3 py-1.5 border border-slate-300 text-xs font-semibold rounded-lg hover:bg-slate-50"
                                @click="openProofPreview(r)">
                            Preview proof
                        </button>
                        <template v-if="r.status === 'submitted'">
                            <button type="button" class="btn-secondary text-xs" @click="act(r, 'verify')">Verify</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(r)">Reject</button>
                        </template>
                        <template v-else-if="r.status === 'verified'">
                            <button type="button" class="btn-primary text-xs" @click="act(r, 'approve')">Approve</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(r)">Reject</button>
                        </template>
                        <template v-else-if="r.status === 'approved'">
                            <button type="button" class="btn-primary text-xs" @click="act(r, 'publish')">Publish</button>
                            <button type="button" class="px-3 py-1.5 border border-red-300 text-red-700 text-xs font-semibold rounded-lg"
                                    @click="reject(r)">Reject</button>
                        </template>
                    </div>
                </div>

                <div v-if="r.toppers && r.toppers.length" class="mt-4 pt-3 border-t border-slate-100">
                    <button type="button" @click="toggleToppers(r.id)" class="text-xs font-bold text-indigo-700 hover:text-indigo-900 flex items-center gap-1">
                        <span>{{ expandedToppers[r.id] ? '▼ Hide Student Marksheets & A1 Achievers' : '▶ Review Student Marksheets & A1 Achievers' }} ({{ r.toppers.length }})</span>
                    </button>

                    <div v-if="expandedToppers[r.id]" class="mt-3 overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="py-2 px-3">Student Name</th>
                                    <th class="py-2 px-3">Roll No</th>
                                    <th class="py-2 px-3">Category / Stream</th>
                                    <th class="py-2 px-3">Uploaded Marksheet</th>
                                    <th class="py-2 px-3">Verification Status</th>
                                    <th class="py-2 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="t in r.toppers" :key="t.id" class="hover:bg-slate-50/50">
                                    <td class="py-2 px-3 font-bold text-slate-900">{{ t.name }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ t.roll_no || '—' }}</td>
                                    <td class="py-2 px-3 text-slate-600 uppercase">{{ t.entry_type }} {{ t.stream ? `(${t.stream})` : '' }}</td>
                                    <td class="py-2 px-3">
                                        <a v-if="t.marksheet_url" :href="t.marksheet_url" target="_blank" class="font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2 py-1 rounded-lg border border-emerald-200 inline-flex items-center gap-1">
                                            📄 View Marksheet ↗
                                        </a>
                                        <span v-else class="text-slate-400">No file uploaded</span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span v-if="t.verification_status === 'verified'" class="font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified ✅</span>
                                        <span v-else-if="t.verification_status === 'rejected'" class="font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700" :title="t.rejection_reason">Rejected ❌</span>
                                        <span v-else class="font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending Verification ⏳</span>
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button v-if="t.verification_status !== 'verified'" type="button" @click="verifyTopper(r, t)" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] rounded-md">
                                                Verify Marks
                                            </button>
                                            <button v-if="t.verification_status !== 'rejected'" type="button" @click="rejectTopper(r, t)" class="px-2.5 py-1 border border-red-300 text-red-700 hover:bg-red-50 font-bold text-[11px] rounded-md">
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <p v-if="!results.data.length" class="text-center text-slate-400 py-10">No board results in this queue.</p>
        </div>

        <div v-if="proofPreview" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/70" @click="closeProofPreview"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[85vh] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Board result proof</p>
                        <h3 class="font-bold text-slate-900 truncate">{{ proofPreview.label }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ proofPreview.typeLabel }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a :href="proofPreview.viewUrl" target="_blank" rel="noopener" class="btn-secondary text-xs">
                            Open in new tab
                        </a>
                        <button type="button" class="btn-ghost text-sm" @click="closeProofPreview">Close</button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-50 overflow-hidden">
                    <img v-if="proofPreview.kind === 'image'" :src="proofPreview.viewUrl" alt="Proof preview" class="w-full h-full object-contain bg-slate-50">
                    <iframe v-else :src="proofPreview.viewUrl" class="w-full h-full bg-slate-50" title="Board result proof preview"></iframe>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    results: Object,
    schoolNames: Object,
    filters: Object,
    statusOptions: Object,
    topperConfigs: { type: Array, default: () => [] },
    defaultTopN: { type: Number, default: 5 },
    selectedClass: { type: Number, default: null },
});

const pageTitle = computed(() => {
    if (props.selectedClass === 12) return 'Class XII Board Result Verification';
    if (props.selectedClass === 10) return 'Class X Board Result Verification';
    return 'Board Result Verification';
});

const statusCounts = computed(() => {
    const counts = { submitted: 0, verified: 0, approved: 0, rejected: 0 };
    (props.results?.data ?? []).forEach((row) => {
        const status = row.status || 'submitted';
        if (counts[status] === undefined) counts[status] = 0;
        counts[status] += 1;
    });
    return counts;
});

const topperCount = computed(() => {
    return (props.results?.data ?? []).reduce((total, row) => total + (row.toppers?.length || 0), 0);
});

const proofPreview = ref(null);

function statusHref(status) {
    const params = new URLSearchParams({ status });
    if (props.selectedClass) params.set('class', props.selectedClass);
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/verification?${params.toString()}`;
}

function openProofPreview(result) {
    if (!result?.latest_proof_url) return;
    proofPreview.value = {
        label: result.latest_proof_label || 'Latest proof',
        kind: proofKind(result.latest_proof_type || result.latest_proof_label || ''),
        typeLabel: proofTypeLabel(result.latest_proof_type || result.latest_proof_label || ''),
        viewUrl: result.latest_proof_url,
    };
}

function openVersionPreview(result, upload) {
    if (!upload) return;
    proofPreview.value = {
        label: upload.file_name || `Version ${upload.version}`,
        kind: proofKind(upload.file_name || upload.file_type || ''),
        typeLabel: proofTypeLabel(upload.file_type || upload.file_name || ''),
        viewUrl: `/sahodaya-admin/${props.sahodaya.id}/board-results/${result.id}/pdf?version=${upload.version}&preview=1`,
    };
}

function closeProofPreview() {
    proofPreview.value = null;
}

function proofKind(input) {
    const ext = String(input || '').split('.').pop()?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'image';
    if (ext === 'pdf') return 'pdf';
    if (['doc', 'docx', 'xls', 'xlsx'].includes(ext)) return 'document';
    return 'file';
}

function proofTypeLabel(input) {
    switch (proofKind(input)) {
        case 'image': return 'Image proof';
        case 'pdf': return 'PDF proof';
        case 'document': return 'Document proof';
        default: return 'Proof file';
    }
}

function act(r, action) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/${r.id}/${action}`, {}, { preserveScroll: true });
}

function reject(r) {
    const reason = window.prompt('Rejection reason (required):');
    if (!reason) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/${r.id}/reject`,
        { rejection_reason: reason },
        { preserveScroll: true },
    );
}

const expandedToppers = reactive({});

function toggleToppers(id) {
    expandedToppers[id] = !expandedToppers[id];
}

function verifyTopper(result, topper) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/${result.id}/toppers/${topper.id}/verify-marksheet`,
        {},
        { preserveScroll: true }
    );
}

function rejectTopper(result, topper) {
    const reason = window.prompt(`Rejection reason for ${topper.name}:`, topper.rejection_reason || 'Marksheet mismatch or invalid document.');
    if (reason === null) return;

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/${result.id}/toppers/${topper.id}/reject-marksheet`,
        { reason },
        { preserveScroll: true }
    );
}
</script>
