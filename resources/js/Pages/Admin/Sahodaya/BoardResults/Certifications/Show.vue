<template>
    <SahodayaAdminLayout title="Certified Package" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${school?.name ?? 'School'} — Class ${pkg.class} · ${pkg.academic_year}`" eyebrow="School-Certified Result Package"
                    :description="`Package v${pkg.version} · ${statusLabel(pkg.status)}`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/certifications`" class="btn-secondary text-sm">← Back to queue</Link>
            </template>
        </PageHeader>

        <!-- Action Panel -->
        <div class="card !p-5 mb-4">
            <h3 class="text-sm font-bold text-slate-800 mb-3">Actions</h3>

            <!-- Submitted — can Verify or Return -->
            <div v-if="pkg.status === 'submitted_to_sahodaya'" class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary text-sm" :disabled="busy" @click="verifyPackage">✅ Verify Package</button>
                <button type="button" class="btn-secondary text-sm text-rose-600" @click="showReturnModal = true">↩️ Return for Correction</button>
            </div>

            <!-- Verified — can Approve or Return -->
            <div v-else-if="pkg.status === 'sahodaya_verified'" class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary text-sm" :disabled="busy" @click="approvePackage">✅ Approve Package</button>
                <button type="button" class="btn-secondary text-sm text-rose-600" @click="showReturnModal = true">↩️ Return for Correction</button>
            </div>

            <!-- Approved — can Publish or Return -->
            <div v-else-if="pkg.status === 'approved'" class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary text-sm" :disabled="busy" @click="publishPackage">🌐 Publish Result</button>
                <button type="button" class="btn-secondary text-sm text-rose-600" @click="showReturnModal = true">↩️ Return for Correction</button>
            </div>

            <!-- Published — can Unpublish -->
            <div v-else-if="pkg.status === 'published'" class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary text-sm text-amber-700" @click="showUnpublishModal = true">↩️ Unpublish for Correction</button>
            </div>

            <!-- Returned / Other -->
            <div v-else>
                <span class="text-sm text-slate-500">Status: <strong>{{ statusLabel(pkg.status) }}</strong> — no further actions available on this package.</span>
            </div>
        </div>

        <!-- Unpublish for Correction Modal -->
        <Modal :show="showUnpublishModal" title="Unpublish for Correction" subtitle="This sends the result back to the school as Rejected so they can fix and resubmit it. Sahodaya-wide rankings will look stale until recalculated from the Toppers hub." @close="showUnpublishModal = false">
            <textarea v-model="unpublishReason" rows="4" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300" placeholder="Reason for unpublishing..."></textarea>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-xs" @click="showUnpublishModal = false">Cancel</button>
                    <button type="button" class="btn-primary text-xs bg-amber-600 hover:bg-amber-700" :disabled="busy || !unpublishReason.trim()" @click="unpublishPackage">Unpublish</button>
                </div>
            </template>
        </Modal>

        <!-- Return for Correction Modal -->
        <Modal :show="showReturnModal" title="Return for Correction" subtitle="Provide a clear reason so the school knows what to fix." @close="showReturnModal = false">
            <textarea v-model="returnReason" rows="4" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300" placeholder="Reason for returning..."></textarea>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-xs" @click="showReturnModal = false">Cancel</button>
                    <button type="button" class="btn-primary text-xs bg-rose-600 hover:bg-rose-700" :disabled="busy || !returnReason.trim()" @click="returnPackage">Return</button>
                </div>
            </template>
        </Modal>

        <div class="card !p-5 mb-4">
            <h3 class="text-sm font-bold text-slate-800 mb-1">Individual Reports <span class="text-slate-400 font-normal">(optional)</span></h3>
            <p class="text-xs text-slate-500 mb-3">Reference documents the school may sign for its own records — not required before submission. The consolidated report below is what's authoritative.</p>
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="py-2 pr-3">Category</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Rows</th>
                        <th class="py-2 pr-3">Signer</th>
                        <th class="py-2 pr-3">Signed At</th>
                        <th class="py-2 pr-3">Data Hash</th>
                        <th class="py-2 pr-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="report in pkg.reports" :key="report.id">
                        <td class="py-2 pr-3 font-semibold text-slate-700">{{ reportLabel(report) }}</td>
                        <td class="py-2 pr-3">
                            <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full" :class="report.status === 'accepted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                {{ report.status.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="py-2 pr-3">{{ report.row_count ?? '—' }}</td>
                        <td class="py-2 pr-3">{{ report.signed_by?.name ?? '—' }} <span v-if="report.signer_role" class="text-slate-400">({{ report.signer_role }})</span></td>
                        <td class="py-2 pr-3 text-slate-500">{{ formatDate(report.signed_at) }}</td>
                        <td class="py-2 pr-3 font-mono text-[10px] text-slate-400">{{ (report.data_hash || '').slice(0, 12) }}…</td>
                        <td class="py-2 pr-3 text-right whitespace-nowrap">
                            <a v-if="report.generated_pdf_path" :href="reportPdfUrl(report, false)" target="_blank" class="text-indigo-600 hover:underline text-xs font-semibold mr-3">Unsigned</a>
                            <a v-if="report.signed_pdf_path" :href="reportPdfUrl(report, true)" target="_blank" class="text-indigo-600 hover:underline text-xs font-semibold">Signed</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Consolidated Certification Report — the one authoritative signed snapshot of the whole package -->
        <div class="card !p-5 mb-4">
            <h3 class="text-sm font-bold text-slate-800 mb-2">Consolidated Certification Report</h3>
            <p class="text-xs text-slate-500 mb-3">The one document required before submission — a signed all-types report covering the full result, downloaded, signed, and uploaded by the Principal/VP.</p>
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <a v-if="pkg.generated_pdf_path" :href="consolidatedPdfUrl(false)" target="_blank" class="text-indigo-600 hover:underline text-xs font-semibold">Unsigned copy</a>
                <a v-if="pkg.signed_pdf_path" :href="consolidatedPdfUrl(true)" target="_blank" class="text-indigo-600 hover:underline text-xs font-semibold">Signed copy</a>
                <span v-if="!pkg.generated_pdf_path" class="text-xs text-amber-600">Not generated by the school yet.</span>
            </div>
            <p v-if="pkg.signed_by" class="text-[11px] text-slate-500">
                Signed by {{ pkg.signed_by?.name ?? '—' }} <span v-if="pkg.signer_role">({{ pkg.signer_role }})</span> on {{ formatDate(pkg.signed_at) }}
            </p>
            <p class="text-[11px] text-slate-400 font-mono mt-1">Package data hash: {{ pkg.data_hash }}</p>
        </div>

        <div v-if="history.length" class="card !p-5">
            <h3 class="text-sm font-bold text-slate-800 mb-2">Prior Versions</h3>
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wide text-slate-400">
                    <tr><th class="py-2 pr-3">Version</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Return Reason</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="h in history" :key="h.id">
                        <td class="py-2 pr-3">v{{ h.version }}</td>
                        <td class="py-2 pr-3">{{ statusLabel(h.status) }}</td>
                        <td class="py-2 pr-3 text-slate-500">{{ h.return_reason ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    package: Object,
    school: Object,
    history: { type: Array, default: () => [] },
});

const pkg = computed(() => props.package);
const showReturnModal = ref(false);
const returnReason = ref('');
const showUnpublishModal = ref(false);
const unpublishReason = ref('');
const busy = ref(false);
const { confirm } = useConfirm();

const base = `/sahodaya-admin/${props.sahodaya.id}/board-results/${pkg.value.board_result_id}`;

function postAction(path, data = {}) {
    busy.value = true;
    router.post(`${base}/${path}`, data, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

async function verifyPackage() {
    if (!await confirm({ message: 'Verify this certified package? Sahodaya can still approve or return it afterward.' })) return;
    postAction('verify');
}

async function approvePackage() {
    if (!await confirm({ message: 'Approve this certified package? It becomes publishable once approved.' })) return;
    postAction('approve');
}

async function publishPackage() {
    if (!await confirm({
        message: 'Publish this result? This notifies the school, issues topper certificates, and recomputes Sahodaya-wide rankings — visible to the public immediately.',
        destructive: true,
        confirmLabel: 'Yes, publish',
    })) return;
    postAction('publish');
}

function returnPackage() {
    postAction('reject', { rejection_reason: returnReason.value });
    showReturnModal.value = false;
}

function unpublishPackage() {
    postAction('unpublish', { reason: unpublishReason.value });
    showUnpublishModal.value = false;
}

function reportLabel(report) {
    const names = { summary: 'Result Summary & Proof', overall_toppers: 'School Topper(s)', subject_toppers: 'Subject-wise Toppers', full_a1: 'Full A1 Achievers' };
    let label = names[report.report_type] || report.report_type;
    if (report.stream) label += ` — ${report.stream.label}`;
    return label;
}

function statusLabel(status) {
    return (status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function reportPdfUrl(report, signed) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/certifications/${pkg.value.id}/reports/${report.id}/pdf?signed=${signed ? 1 : 0}&preview=1`;
}

function consolidatedPdfUrl(signed) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/certifications/${pkg.value.id}/consolidated/pdf?signed=${signed ? 1 : 0}&preview=1`;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
