<template>
    <SchoolAdminLayout title="Principal Verification" :school="school" :show-header-title="false">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <Link :href="`/school-admin/${school.id}/board-results/${boardResult.id}/principal-verification`" class="text-xs text-gray-400 hover:text-gray-600">&larr; Back to checklist</Link>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight mt-1">Consolidated Certification Report</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Class {{ boardResult.class }} · {{ boardResult.examination_type }} — {{ boardResult.academic_year }} · Package v{{ package.version }}
                    <span v-if="!canSign" class="text-amber-600"> · view only — only the Principal or an authorized Vice Principal can sign</span>
                </p>
            </div>
            <span class="text-[10px] font-bold uppercase px-2.5 py-1 rounded-full shrink-0" :class="reportPillClass(consolidatedStatus)">
                {{ reportStatusLabel(consolidatedStatus) }}
            </span>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-[11px] text-gray-400 mb-4">
                One all-types signed document covering the full result — the only report required before submitting to Sahodaya.
            </p>

            <div class="flex items-center gap-2 mb-5 text-[10px] text-gray-400">
                <span :class="package.generated_pdf_path ? 'text-emerald-600 font-semibold' : 'font-semibold text-gray-700'">1. Generate</span>
                <span>→</span>
                <span :class="['awaiting_consolidated_signature','school_certified'].includes(package.status) ? 'text-emerald-600 font-semibold' : 'text-gray-400'">2. Print &amp; Sign</span>
                <span>→</span>
                <span :class="package.status === 'school_certified' ? 'text-emerald-600 font-semibold' : 'text-gray-400'">3. ✓ Uploaded</span>
            </div>

            <div class="flex flex-wrap items-center gap-2 mb-4">
                <button
                    v-if="canSign && package.status !== 'school_certified'"
                    type="button" class="btn-primary text-xs" :disabled="busy"
                    @click="generateConsolidated"
                >
                    🖨️ {{ package.generated_pdf_path ? 'Regenerate' : 'Generate' }} Consolidated PDF
                </button>

                <a v-if="package.generated_pdf_path" :href="consolidatedPdfUrl() + '?preview=true'" target="_blank" class="btn-primary text-xs">👁️ Preview PDF</a>
                <a v-if="package.generated_pdf_path" :href="consolidatedPdfUrl()" target="_blank" class="btn-secondary text-xs">⬇️ Download &amp; Print</a>

                <a v-if="package.signed_pdf_path" :href="consolidatedSignedPdfUrl() + '?preview=true'" target="_blank" class="btn-primary text-xs">👁️ Preview Signed Copy</a>
                <a v-if="package.signed_pdf_path" :href="consolidatedSignedPdfUrl()" target="_blank" class="btn-secondary text-xs">⬇️ Download Signed Copy</a>

                <span v-if="package.status === 'school_certified'" class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                    ✓ Signed &amp; Uploaded
                </span>
            </div>

            <div v-if="canSign && package.generated_pdf_path && package.status !== 'school_certified'"
                 class="border border-dashed border-gray-300 rounded-xl p-4 space-y-3">
                <p class="text-xs font-bold text-gray-700">Declaration and signed upload</p>
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
                    The uploaded document bears the authorized signature and school seal.
                </label>
                <label class="btn-primary text-xs cursor-pointer inline-flex items-center"
                       :class="{ 'opacity-50 pointer-events-none': !declarationsComplete }">
                    📤 Upload Signed Consolidated Copy
                    <input type="file" accept="application/pdf" class="hidden" :disabled="!declarationsComplete" @change="uploadSignedConsolidated">
                </label>
            </div>

            <div v-if="package.status === 'school_certified'" class="mt-4">
                <Link :href="`/school-admin/${school.id}/board-results/${boardResult.id}/principal-verification`" class="btn-primary text-xs inline-flex items-center gap-1.5">
                    ← Back to checklist to submit to Sahodaya
                </Link>
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
});

const busy = ref(false);
const declarations = ref({ figures: false, details: false, seal: false });
const declarationsComplete = computed(() => declarations.value.figures && declarations.value.details && declarations.value.seal);

const consolidatedStatus = computed(() => {
    if (props.package?.status === 'school_certified') return 'accepted';
    if (props.package?.generated_pdf_path) return 'generated';
    return 'pending';
});

const base = `/school-admin/${props.school.id}/board-results/${props.boardResult.id}`;

function consolidatedPdfUrl() {
    return `${base}/certification/consolidated/pdf`;
}
function consolidatedSignedPdfUrl() {
    return `${base}/certification/consolidated/signed-pdf`;
}

function reportStatusLabel(status) {
    const labels = {
        pending: 'Pending',
        generated: 'Generated',
        signed_uploaded: 'Signed — Awaiting Acceptance',
        accepted: 'Accepted',
    };
    return labels[status] || status;
}

function reportPillClass(status) {
    if (status === 'accepted') return 'bg-emerald-100 text-emerald-700';
    if (status === 'generated') return 'bg-amber-100 text-amber-700';
    return 'bg-gray-100 text-gray-500';
}

function generateConsolidated() {
    busy.value = true;
    router.post(`${base}/certification/consolidated/generate`, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function uploadSignedConsolidated(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    busy.value = true;
    router.post(`${base}/certification/consolidated/signed-pdf`, {
        signed_pdf: file,
        declaration_figures_checked: declarations.value.figures,
        declaration_details_correct: declarations.value.details,
        declaration_signature_seal: declarations.value.seal,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { busy.value = false; event.target.value = ''; },
    });
}
</script>
