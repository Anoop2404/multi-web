<template>
    <SchoolAdminLayout title="Principal Verification" :school="school" :show-header-title="false">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <Link :href="`/school-admin/${school.id}/board-results/principal-verification`" class="text-xs text-gray-400 hover:text-gray-600">&larr; Back</Link>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight mt-1">
                    Class {{ boardResult.class }} · {{ boardResult.examination_type }} — {{ boardResult.academic_year }}
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Package v{{ package.version }} ·
                    <span class="font-semibold">{{ statusLabel(package.status) }}</span>
                    <span v-if="!canSign" class="text-amber-600"> · view only — only the Principal or an authorized Vice Principal can sign</span>
                </p>
            </div>
        </div>

        <!-- Data incomplete warning (when total_appeared = 0 etc.) -->
        <div v-if="validationErrors.length" class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 space-y-3">
            <p class="text-sm font-bold text-amber-800">⚠️ Some data is missing — reports may be empty</p>
            <ul class="list-disc pl-5 space-y-1 text-xs text-amber-700">
                <li v-for="(err, i) in validationErrors" :key="i">{{ err }}</li>
            </ul>
            <Link
                :href="`/school-admin/${school.id}/board-results?class=${boardResult.class}&academic_year=${encodeURIComponent(boardResult.academic_year || '')}`"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-indigo-700 bg-white border border-amber-300 hover:bg-amber-50 transition"
            >
                ✏️ Fill Missing Data →
            </Link>
        </div>

        <!-- Already submitted banner -->
        <div v-if="['submitted_to_sahodaya','sahodaya_verified','approved','published'].includes(package.status)"
             class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6">
            <p class="text-sm font-bold text-emerald-800">✅ Submitted to Sahodaya</p>
            <p class="text-xs text-emerald-600 mt-1">Submitted on {{ formatDate(package.submitted_at) }}. Sahodaya is now reviewing the certified package.</p>
        </div>

        <!-- Changes requested — returned by Sahodaya -->
        <div v-if="package.status === 'leadership_changes_requested'" class="bg-rose-50 border border-rose-200 rounded-2xl p-5 mb-6">
            <p class="text-sm font-bold text-rose-700">📋 Correction Required</p>
            <p class="text-xs text-rose-600 mt-1">{{ package.return_reason || 'Sahodaya has requested corrections. Fix the data and re-submit.' }}</p>
            <p class="text-xs text-gray-500 mt-2">Go to the result entry page, fix the data, then click the button below to re-submit for verification.</p>
            <div class="flex gap-2 mt-3 flex-wrap">
                <Link
                    :href="`/school-admin/${school.id}/board-results?class=${boardResult.class}&academic_year=${encodeURIComponent(boardResult.academic_year || '')}`"
                    class="btn-secondary text-xs"
                >✏️ Fix Data</Link>
                <button type="button" class="btn-primary text-xs" :disabled="busy" @click="resubmitAfterCorrection">
                    🔄 Re-submit for Verification
                </button>
            </div>
        </div>

        <!-- Individual Reports -->
        <div v-if="activeReports.length" class="space-y-4 mb-8">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Individual Reports</h2>

            <div v-for="report in activeReports" :key="report.id"
                 class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">{{ reportLabel(report) }}</h3>
                        <p class="text-[11px] text-gray-400">
                            <span v-if="report.row_count !== null">{{ report.row_count }} row(s) captured</span>
                            <span v-else>Not yet generated</span>
                        </p>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full" :class="reportPillClass(report.status)">
                        {{ reportStatusLabel(report.status) }}
                    </span>
                </div>

                <!-- Step indicator -->
                <div class="flex items-center gap-2 mb-4 text-[10px] text-gray-400">
                    <span :class="report.status !== 'pending' ? 'text-emerald-600 font-semibold' : 'font-semibold text-gray-700'">1. Generate</span>
                    <span>→</span>
                    <span :class="['signed_uploaded','accepted'].includes(report.status) ? 'text-emerald-600 font-semibold' : 'text-gray-400'">2. Print & Sign</span>
                    <span>→</span>
                    <span :class="report.status === 'accepted' ? 'text-emerald-600 font-semibold' : 'text-gray-400'">3. Upload Signed Copy</span>
                    <span>→</span>
                    <span :class="report.status === 'accepted' ? 'text-emerald-600 font-semibold' : 'text-gray-400'">4. ✓ Accepted</span>
                </div>

                <div v-if="report.review_notes && report.status === 'changes_requested'"
                     class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-2 mb-3">
                    Returned: {{ report.review_notes }}
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Step 1: Generate -->
                    <button
                        v-if="['pending', 'changes_requested'].includes(report.status)"
                        type="button" class="btn-primary text-xs" :disabled="busy"
                        @click="generateReport(report)"
                    >
                        🖨️ Generate Report PDF
                    </button>

                    <!-- Regenerate -->
                    <button
                        v-if="report.status === 'generated'"
                        type="button" class="btn-secondary text-xs" :disabled="busy"
                        @click="generateReport(report)"
                    >
                        Regenerate PDF
                    </button>

                    <!-- Step 2: Preview & Download to print & sign -->
                    <a
                        v-if="report.generated_pdf_path"
                        :href="reportPdfUrl(report) + '?preview=true'" target="_blank"
                        class="btn-primary text-xs"
                    >
                        👁️ Preview PDF
                    </a>
                    <a
                        v-if="report.generated_pdf_path"
                        :href="reportPdfUrl(report)" target="_blank"
                        class="btn-secondary text-xs"
                    >
                        ⬇️ Download & Print
                    </a>

                    <!-- Step 3: Upload signed copy -->
                    <label v-if="canSign && ['generated', 'changes_requested'].includes(report.status)"
                           class="btn-primary text-xs cursor-pointer">
                        📤 Upload Signed Copy
                        <input type="file" accept="application/pdf,image/*" class="hidden" @change="uploadSigned(report, $event)">
                    </label>

                    <!-- View signed copy -->
                    <a
                        v-if="report.signed_pdf_path"
                        :href="reportSignedPdfUrl(report) + '?preview=true'" target="_blank"
                        class="btn-primary text-xs"
                    >
                        👁️ Preview Signed Copy
                    </a>
                    <a
                        v-if="report.signed_pdf_path"
                        :href="reportSignedPdfUrl(report)" target="_blank"
                        class="btn-secondary text-xs"
                    >
                        ⬇️ Download Signed Copy
                    </a>

                    <!-- Step 4: Accept -->
                    <button
                        v-if="canSign && report.status === 'signed_uploaded'"
                        type="button" class="btn-primary text-xs" :disabled="busy"
                        @click="acceptReport(report)"
                    >
                        ✅ Verify & Accept
                    </button>

                    <button
                        v-if="canSign && report.status === 'signed_uploaded'"
                        type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-700" :disabled="busy"
                        @click="returnReport(report)"
                    >
                        Return for Correction
                    </button>

                    <!-- Accepted badge -->
                    <span v-if="report.status === 'accepted'" class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                        ✓ Signed & Accepted
                    </span>
                </div>
            </div>
        </div>

        <!-- Submit to Sahodaya -->
        <div v-if="showSubmitSection" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 mb-1">Submit to Sahodaya</h2>

            <div v-if="!allReportsAccepted" class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                All reports above must be signed and accepted before you can submit.
                <br><span class="font-semibold">{{ acceptedCount }}/{{ activeReports.length }} reports accepted.</span>
            </div>

            <div v-else class="space-y-4">
                <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                    ✅ All {{ activeReports.length }} reports are signed and accepted. You can now submit to Sahodaya.
                </p>

                <div class="border border-dashed border-gray-300 rounded-xl p-4 space-y-3">
                    <p class="text-xs font-bold text-gray-700">Declaration before submitting</p>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input v-model="declarations.figures" type="checkbox" class="mt-0.5">
                        I have verified the figures against the official board result.
                    </label>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input v-model="declarations.details" type="checkbox" class="mt-0.5">
                        The topper and Full A1 details are correct and complete.
                    </label>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input v-model="declarations.seal" type="checkbox" class="mt-0.5">
                        All uploaded signed documents bear the authorized signature and school seal.
                    </label>
                </div>

                <button
                    v-if="canSign"
                    type="button" class="btn-primary" :disabled="busy || !declarationsComplete"
                    @click="submitPackage"
                >
                    📨 Submit Certified Package to Sahodaya
                </button>
            </div>
        </div>

        <!-- Return report modal -->
        <Modal :show="!!returnTarget" title="Return report for correction" @close="returnTarget = null">
            <textarea v-model="returnReason" rows="3" class="field w-full text-xs" placeholder="Reason for returning this report..."></textarea>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-xs" @click="returnTarget = null">Cancel</button>
                    <button type="button" class="btn-primary text-xs" :disabled="!returnReason.trim()" @click="confirmReturnReport">Return</button>
                </div>
            </template>
        </Modal>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    boardResult: Object,
    package: Object,
    canSign: Boolean,
    validationErrors: { type: Array, default: () => [] },
    allReportsAccepted: Boolean,
});

const busy = ref(false);
const returnTarget = ref(null);
const returnReason = ref('');
const declarations = ref({ figures: false, details: false, seal: false });

const declarationsComplete = computed(() => declarations.value.figures && declarations.value.details && declarations.value.seal);

const activeReports = computed(() =>
    (props.package?.reports ?? []).filter(r => r.status !== 'superseded')
);

const acceptedCount = computed(() =>
    activeReports.value.filter(r => r.status === 'accepted').length
);

const showSubmitSection = computed(() => {
    const nonTerminal = !['submitted_to_sahodaya', 'sahodaya_verified', 'approved', 'published'].includes(props.package.status);
    return nonTerminal && activeReports.value.length > 0;
});

const base = computed(() => `/school-admin/${props.school.id}/board-results/${props.boardResult.id}`);

function reportPdfUrl(report) {
    return `${base.value}/certification/reports/${report.id}/pdf`;
}
function reportSignedPdfUrl(report) {
    return `${base.value}/certification/reports/${report.id}/signed-pdf`;
}

function reportLabel(report) {
    const names = {
        summary: 'Result Summary & Proof',
        overall_toppers: 'School Topper(s)',
        subject_toppers: 'Subject-wise Toppers',
        full_a1: 'Full A1 Achievers',
    };
    let label = names[report.report_type] || report.report_type;
    if (report.stream) label += ` — ${report.stream.label}`;
    return label;
}

function statusLabel(status) {
    return (status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function reportStatusLabel(status) {
    const labels = {
        pending: 'Pending',
        generated: 'Generated',
        signed_uploaded: 'Signed — Awaiting Acceptance',
        accepted: 'Accepted',
        changes_requested: 'Changes Requested',
        superseded: 'Superseded',
    };
    return labels[status] || status;
}

function reportPillClass(status) {
    if (status === 'accepted') return 'bg-emerald-100 text-emerald-700';
    if (status === 'changes_requested') return 'bg-rose-100 text-rose-700';
    if (status === 'signed_uploaded') return 'bg-indigo-100 text-indigo-700';
    if (status === 'generated') return 'bg-amber-100 text-amber-700';
    return 'bg-gray-100 text-gray-500';
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function resubmitAfterCorrection() {
    busy.value = true;
    router.post(`${base.value}/request-leadership-review`, {}, {
        onFinish: () => { busy.value = false; },
    });
}

function generateReport(report) {
    busy.value = true;
    router.post(`${base.value}/certification/reports/${report.id}/generate`, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function uploadSigned(report, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    busy.value = true;
    router.post(`${base.value}/certification/reports/${report.id}/signed-pdf`, { signed_pdf: file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { busy.value = false; event.target.value = ''; },
    });
}

function acceptReport(report) {
    busy.value = true;
    router.post(`${base.value}/certification/reports/${report.id}/accept`, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function returnReport(report) {
    returnTarget.value = report;
    returnReason.value = '';
}

function confirmReturnReport() {
    if (!returnTarget.value) return;
    busy.value = true;
    router.post(`${base.value}/certification/reports/${returnTarget.value.id}/return`, { reason: returnReason.value }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; returnTarget.value = null; },
    });
}

function submitPackage() {
    busy.value = true;
    router.post(`${base.value}/certification/submit`, {}, {
        onFinish: () => { busy.value = false; },
    });
}
</script>
