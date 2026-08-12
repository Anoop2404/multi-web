<template>
    <SchoolAdminLayout title="Principal Verification" :school="school" :show-header-title="false">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <Link :href="`/school-admin/${school.id}/board-results/principal-verification`" class="text-xs text-gray-400 hover:text-gray-600">&larr; Back</Link>
                </div>
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

        <!-- Draft: not yet sent for review -->
        <div v-if="package.status === 'draft'" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
            <h2 class="text-base font-bold text-gray-900 mb-2">Send for Leadership Review</h2>
            <div v-if="validationErrors.length" class="bg-rose-50 border border-rose-200 rounded-xl p-4 mb-4">
                <p class="text-xs font-bold text-rose-700 mb-2">Fix these before sending for review:</p>
                <ul class="list-disc pl-5 space-y-1 text-xs text-rose-700">
                    <li v-for="(err, i) in validationErrors" :key="i">{{ err }}</li>
                </ul>
            </div>
            <p v-else class="text-sm text-gray-500 mb-4">
                The result looks complete. Sending for review freezes the current figures and toppers so the Principal/Vice Principal can generate and sign each report.
            </p>
            <button
                type="button"
                class="btn-primary"
                :disabled="validationErrors.length > 0 || sendingReview"
                @click="sendForReview"
            >
                Send for Leadership Review
            </button>
        </div>

        <!-- Changes requested by leadership -->
        <div v-if="package.status === 'leadership_changes_requested'" class="bg-rose-50 border border-rose-200 rounded-2xl p-5 mb-6">
            <p class="text-sm font-bold text-rose-700">Changes requested</p>
            <p class="text-xs text-rose-600 mt-1">{{ package.return_reason }}</p>
            <p class="text-xs text-gray-500 mt-2">Correct the result on the data entry screen, then send it for review again.</p>
        </div>

        <!-- Report checklist -->
        <div v-if="showReports" class="space-y-4 mb-8">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Individual Reports</h2>
            <div v-for="report in package.reports.filter(r => r.status !== 'superseded')" :key="report.id" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ reportLabel(report) }}
                        </h3>
                        <p class="text-[11px] text-gray-400">{{ report.row_count ?? 0 }} row(s) captured at generation</p>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full" :class="reportPillClass(report.status)">
                        {{ report.status.replace('_', ' ') }}
                    </span>
                </div>

                <div v-if="report.review_notes && report.status === 'changes_requested'" class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-2 mb-3">
                    Returned: {{ report.review_notes }}
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="['pending'].includes(report.status)"
                        type="button" class="btn-secondary text-xs" :disabled="busy"
                        @click="generateReport(report)"
                    >
                        Generate Report PDF
                    </button>

                    <a
                        v-if="report.generated_pdf_path"
                        :href="reportPdfUrl(report)" target="_blank"
                        class="btn-secondary text-xs"
                    >
                        Download Unsigned PDF
                    </a>

                    <button
                        v-if="['generated'].includes(report.status)"
                        type="button" class="btn-secondary text-xs" :disabled="busy"
                        @click="generateReport(report)"
                    >
                        Regenerate PDF
                    </button>

                    <label v-if="canSign && ['generated', 'changes_requested'].includes(report.status)" class="btn-secondary text-xs cursor-pointer">
                        Upload Signed Copy
                        <input type="file" accept="application/pdf" class="hidden" @change="uploadSigned(report, $event)">
                    </label>

                    <a
                        v-if="report.signed_pdf_path"
                        :href="reportSignedPdfUrl(report)" target="_blank"
                        class="btn-secondary text-xs"
                    >
                        View Signed Copy
                    </a>

                    <button
                        v-if="canSign && report.status === 'signed_uploaded'"
                        type="button" class="btn-primary text-xs" :disabled="busy"
                        @click="acceptReport(report)"
                    >
                        Verify &amp; Accept
                    </button>

                    <button
                        v-if="canSign && report.status === 'signed_uploaded'"
                        type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-700" :disabled="busy"
                        @click="returnReport(report)"
                    >
                        Return for Correction
                    </button>
                </div>
            </div>
        </div>

        <!-- Final certification -->
        <div v-if="showFinalCertification" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 mb-1">Final Certification</h2>
            <p class="text-xs text-gray-500 mb-4">
                Once every required report is signed and accepted, generate the all-types consolidated report, sign it, and submit the certified package.
            </p>

            <div v-if="!allReportsAccepted && !['awaiting_consolidated_signature', 'school_certified', 'submitted_to_sahodaya', 'sahodaya_verified', 'approved', 'published'].includes(package.status)" class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-3">
                Every individual report above must be signed and accepted before the consolidated report can be generated.
            </div>

            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="['awaiting_report_signatures', 'individual_reports_signed'].includes(package.status)"
                        type="button" class="btn-secondary text-xs" :disabled="busy"
                        @click="generateConsolidated"
                    >
                        Generate Consolidated PDF
                    </button>

                    <a v-if="package.generated_pdf_path" :href="consolidatedPdfUrl" target="_blank" class="btn-secondary text-xs">
                        Download Unsigned Consolidated PDF
                    </a>

                    <a v-if="package.signed_pdf_path" :href="consolidatedSignedPdfUrl" target="_blank" class="btn-secondary text-xs">
                        View Signed Consolidated PDF
                    </a>
                </div>

                <div v-if="canSign && package.status === 'awaiting_consolidated_signature'" class="border border-dashed border-gray-300 rounded-xl p-4 space-y-3">
                    <p class="text-xs font-bold text-gray-700">Upload Signed Consolidated Report &amp; Declare</p>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input v-model="declarations.figures" type="checkbox" class="mt-0.5">
                        I have checked the figures against the official board result.
                    </label>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input v-model="declarations.details" type="checkbox" class="mt-0.5">
                        The topper, subject-wise, stream-wise, and Full A1 details are correct.
                    </label>
                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input v-model="declarations.seal" type="checkbox" class="mt-0.5">
                        The uploaded document bears the authorized signature and school seal.
                    </label>
                    <input ref="consolidatedFileRef" type="file" accept="application/pdf" class="text-xs">
                    <div>
                        <button type="button" class="btn-primary text-xs" :disabled="busy || !declarationsComplete" @click="uploadSignedConsolidated">
                            Sign-off &amp; Upload
                        </button>
                    </div>
                </div>

                <button
                    v-if="canSign && package.status === 'school_certified'"
                    type="button" class="btn-primary" :disabled="busy"
                    @click="submitPackage"
                >
                    Submit Certified Package to Sahodaya
                </button>

                <div v-if="['submitted_to_sahodaya','sahodaya_verified','approved','published'].includes(package.status)" class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                    Submitted to Sahodaya on {{ formatDate(package.submitted_at) }}.
                </div>
            </div>
        </div>

        <!-- Return report modal (simple inline reason prompt) -->
        <div v-if="returnTarget" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="returnTarget = null">
            <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
                <h3 class="text-sm font-bold text-gray-900 mb-2">Return report for correction</h3>
                <textarea v-model="returnReason" rows="3" class="field w-full text-xs" placeholder="Reason for returning this report..."></textarea>
                <div class="flex justify-end gap-2 mt-3">
                    <button type="button" class="btn-secondary text-xs" @click="returnTarget = null">Cancel</button>
                    <button type="button" class="btn-primary text-xs" :disabled="!returnReason.trim()" @click="confirmReturnReport">Return</button>
                </div>
            </div>
        </div>
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
const sendingReview = ref(false);
const returnTarget = ref(null);
const returnReason = ref('');
const consolidatedFileRef = ref(null);
const declarations = ref({ figures: false, details: false, seal: false });

const declarationsComplete = computed(() => declarations.value.figures && declarations.value.details && declarations.value.seal);

const showReports = computed(() => !['draft'].includes(props.package.status) && props.package.reports?.length);
const showFinalCertification = computed(() => !['draft', 'leadership_changes_requested'].includes(props.package.status));

const base = computed(() => `/school-admin/${props.school.id}/board-results/${props.boardResult.id}`);
const consolidatedPdfUrl = computed(() => `${base.value}/certification/consolidated/pdf`);
const consolidatedSignedPdfUrl = computed(() => `${base.value}/certification/consolidated/signed-pdf`);

function reportPdfUrl(report) {
    return `${base.value}/certification/reports/${report.id}/pdf`;
}
function reportSignedPdfUrl(report) {
    return `${base.value}/certification/reports/${report.id}/signed-pdf`;
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

function sendForReview() {
    sendingReview.value = true;
    router.post(`${base.value}/request-leadership-review`, {}, {
        onFinish: () => { sendingReview.value = false; },
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

function generateConsolidated() {
    busy.value = true;
    router.post(`${base.value}/certification/consolidated/generate`, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function uploadSignedConsolidated() {
    const file = consolidatedFileRef.value?.files?.[0];
    if (!file || !declarationsComplete.value) return;
    busy.value = true;
    router.post(`${base.value}/certification/consolidated/signed-pdf`, {
        signed_pdf: file,
        declaration_figures_checked: declarations.value.figures,
        declaration_details_correct: declarations.value.details,
        declaration_signature_seal: declarations.value.seal,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function submitPackage() {
    busy.value = true;
    router.post(`${base.value}/certification/submit`, {}, {
        onFinish: () => { busy.value = false; },
    });
}
</script>
