<template>
    <SchoolAdminLayout title="Principal Verification" :school="school" :show-header-title="false">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <Link :href="`/school-admin/${school.id}/board-results/${boardResult.id}/principal-verification`" class="text-xs text-gray-400 hover:text-gray-600">&larr; Back to checklist</Link>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight mt-1">{{ reportLabel(report) }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Class {{ boardResult.class }} · {{ boardResult.examination_type }} — {{ boardResult.academic_year }} · Package v{{ package.version }}
                    <span v-if="!canSign" class="text-amber-600"> · view only — only the Principal or an authorized Vice Principal can sign</span>
                </p>
            </div>
            <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full shrink-0" :class="reportPillClass(report.status)">
                {{ reportStatusLabel(report.status) }}
            </span>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-[11px] text-gray-400 mb-4">
                <span v-if="report.row_count !== null">{{ report.row_count }} row(s) captured</span>
                <span v-else>Not yet generated</span>
            </p>

            <!-- Step indicator -->
            <div class="flex items-center gap-2 mb-5 text-[10px] text-gray-400">
                <span :class="report.status !== 'pending' ? 'text-emerald-600 font-semibold' : 'font-semibold text-gray-700'">1. Generate</span>
                <span>→</span>
                <span :class="['signed_uploaded','accepted'].includes(report.status) ? 'text-emerald-600 font-semibold' : 'text-gray-400'">2. Print & Sign</span>
                <span>→</span>
                <span :class="report.status === 'accepted' ? 'text-emerald-600 font-semibold' : 'text-gray-400'">3. Upload Signed Copy</span>
                <span>→</span>
                <span :class="report.status === 'accepted' ? 'text-emerald-600 font-semibold' : 'text-gray-400'">4. ✓ Accepted</span>
            </div>

            <div v-if="report.review_notes && report.status === 'changes_requested'"
                 class="text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-3 mb-4">
                Returned: {{ report.review_notes }}
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Step 1: Generate -->
                <button
                    v-if="['pending', 'changes_requested'].includes(report.status)"
                    type="button" class="btn-primary text-xs" :disabled="busy"
                    @click="generateReport"
                >
                    🖨️ Generate Report PDF
                </button>

                <!-- Regenerate -->
                <button
                    v-if="report.status === 'generated'"
                    type="button" class="btn-secondary text-xs" :disabled="busy"
                    @click="generateReport"
                >
                    Regenerate PDF
                </button>

                <!-- Step 2: Preview & Download to print & sign -->
                <a v-if="report.generated_pdf_path" :href="reportPdfUrl() + '?preview=true'" target="_blank" class="btn-primary text-xs">👁️ Preview PDF</a>
                <a v-if="report.generated_pdf_path" :href="reportPdfUrl()" target="_blank" class="btn-secondary text-xs">⬇️ Download & Print</a>

                <!-- Step 3: Upload signed copy -->
                <label v-if="canSign && ['generated', 'changes_requested'].includes(report.status)"
                       class="btn-primary text-xs cursor-pointer">
                    📤 Upload Signed Copy
                    <input type="file" accept="application/pdf,image/*" class="hidden" @change="uploadSigned">
                </label>

                <!-- View signed copy -->
                <a v-if="report.signed_pdf_path" :href="reportSignedPdfUrl() + '?preview=true'" target="_blank" class="btn-primary text-xs">👁️ Preview Signed Copy</a>
                <a v-if="report.signed_pdf_path" :href="reportSignedPdfUrl()" target="_blank" class="btn-secondary text-xs">⬇️ Download Signed Copy</a>

                <!-- Step 4: Accept -->
                <button
                    v-if="canSign && report.status === 'signed_uploaded'"
                    type="button" class="btn-primary text-xs" :disabled="busy"
                    @click="acceptReport"
                >
                    ✅ Verify & Accept
                </button>

                <button
                    v-if="canSign && report.status === 'signed_uploaded'"
                    type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-700" :disabled="busy"
                    @click="showReturnModal = true"
                >
                    Return for Correction
                </button>

                <!-- Accepted badge -->
                <span v-if="report.status === 'accepted'" class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                    ✓ Signed & Accepted
                </span>
            </div>
        </div>

        <!-- Return report modal -->
        <Modal :show="showReturnModal" title="Return report for correction" @close="showReturnModal = false">
            <textarea v-model="returnReason" rows="3" class="field w-full text-xs" placeholder="Reason for returning this report..."></textarea>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary text-xs" @click="showReturnModal = false">Cancel</button>
                    <button type="button" class="btn-primary text-xs" :disabled="!returnReason.trim()" @click="confirmReturnReport">Return</button>
                </div>
            </template>
        </Modal>
    </SchoolAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    boardResult: Object,
    package: Object,
    report: Object,
    canSign: Boolean,
});

const busy = ref(false);
const showReturnModal = ref(false);
const returnReason = ref('');

const base = `/school-admin/${props.school.id}/board-results/${props.boardResult.id}`;

function reportPdfUrl() {
    return `${base}/certification/reports/${props.report.id}/pdf`;
}
function reportSignedPdfUrl() {
    return `${base}/certification/reports/${props.report.id}/signed-pdf`;
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

function generateReport() {
    busy.value = true;
    router.post(`${base}/certification/reports/${props.report.id}/generate`, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function uploadSigned(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    busy.value = true;
    router.post(`${base}/certification/reports/${props.report.id}/signed-pdf`, { signed_pdf: file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { busy.value = false; event.target.value = ''; },
    });
}

function acceptReport() {
    busy.value = true;
    router.post(`${base}/certification/reports/${props.report.id}/accept`, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function confirmReturnReport() {
    busy.value = true;
    router.post(`${base}/certification/reports/${props.report.id}/return`, { reason: returnReason.value }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; showReturnModal.value = false; },
    });
}
</script>
