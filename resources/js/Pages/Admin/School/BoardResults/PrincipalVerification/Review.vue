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

        <BoardResultWorkflowStepper :board-result="boardResult" :certification-package="package" />

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

        <!-- Individual reports — optional reference documents, not required to submit -->
        <div v-if="activeReports.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-6 divide-y divide-gray-100">
            <div class="px-5 py-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide">
                    Individual Reports <span class="text-gray-400 font-normal normal-case">(optional)</span>
                </h2>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    For your own school records — not required before submitting. {{ acceptedCount }}/{{ activeReports.length }} signed and accepted.
                </p>
            </div>

            <Link v-for="report in activeReports" :key="report.id"
                  :href="`${base}/principal-verification/reports/${report.id}`"
                  class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-gray-50 transition">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ reportLabel(report) }}</p>
                    <p class="text-[11px] text-gray-400">
                        <span v-if="report.row_count !== null">{{ report.row_count }} row(s) captured</span>
                        <span v-else>Not yet generated</span>
                        <span v-if="report.review_notes && report.status === 'changes_requested'" class="text-rose-500"> · {{ report.review_notes }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full" :class="reportPillClass(report.status)">
                        {{ reportStatusLabel(report.status) }}
                    </span>
                    <span class="text-xs font-bold text-indigo-600">Review →</span>
                </div>
            </Link>
        </div>

        <!-- Consolidated report — the one document required before submitting -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-8">
            <Link :href="`${base}/principal-verification/consolidated`"
                  class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-gray-50 transition">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900">Consolidated Certification Report</p>
                    <p class="text-[11px] text-gray-400">Required — one signed document covering the full result.</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full" :class="reportPillClass(consolidatedStatus)">
                        {{ reportStatusLabel(consolidatedStatus) }}
                    </span>
                    <span class="text-xs font-bold text-indigo-600">Review →</span>
                </div>
            </Link>
        </div>

        <!-- Submit to Sahodaya -->
        <div v-if="showSubmitSection" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 mb-1">Submit to Sahodaya</h2>

            <div v-if="package.status !== 'school_certified'" class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-3">
                The signed consolidated report above must be generated, signed, and uploaded before you can submit.
            </div>

            <div v-else class="space-y-4">
                <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                    ✅ The signed consolidated report is uploaded. You can now submit to Sahodaya.
                </p>

                <button
                    v-if="canSign"
                    type="button" class="btn-primary" :disabled="busy"
                    @click="submitPackage"
                >
                    📨 Submit Certified Package to Sahodaya
                </button>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import BoardResultWorkflowStepper from '@/Components/BoardResults/BoardResultWorkflowStepper.vue';

const props = defineProps({
    school: Object,
    boardResult: Object,
    package: Object,
    canSign: Boolean,
    validationErrors: { type: Array, default: () => [] },
    allReportsAccepted: Boolean,
});

const busy = ref(false);

const activeReports = computed(() =>
    (props.package?.reports ?? []).filter(r => r.status !== 'superseded')
);

const acceptedCount = computed(() =>
    activeReports.value.filter(r => r.status === 'accepted').length
);

const consolidatedStatus = computed(() => {
    if (props.package?.status === 'school_certified') return 'accepted';
    if (props.package?.generated_pdf_path) return 'generated';
    return 'pending';
});

const showSubmitSection = computed(() =>
    !['submitted_to_sahodaya', 'sahodaya_verified', 'approved', 'published'].includes(props.package.status)
);

const base = `/school-admin/${props.school.id}/board-results/${props.boardResult.id}`;

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
    router.post(`${base}/request-leadership-review`, {}, {
        onFinish: () => { busy.value = false; },
    });
}

function submitPackage() {
    busy.value = true;
    router.post(`${base}/certification/submit`, {}, {
        onFinish: () => { busy.value = false; },
    });
}
</script>
