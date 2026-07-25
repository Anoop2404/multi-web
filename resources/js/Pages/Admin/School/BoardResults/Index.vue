<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results" :description="pageDescription" />

        <p v-if="selectedClass" class="text-sm -mt-2 mb-4">
            <Link :href="`/school-admin/${school.id}/board-results?class=${selectedClass === 12 ? 10 : 12}`" class="text-indigo-600 hover:underline font-medium">
                Switch to {{ selectedClass === 12 ? 'Class X' : 'Class XII' }} results →
            </Link>
        </p>

        <div class="space-y-6">
            <!-- Step 1: pick the academic year to work on -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-1">Find or start a result</h3>
                <p class="text-xs text-gray-500 mb-4">Pick the academic year and search — it'll load the existing result to edit, or let you start a new one.</p>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
                    <div v-if="!selectedClass" class="w-40">
                        <label class="form-label mb-1.5">Class *</label>
                        <select v-model="searchClass" required class="field">
                            <option value="10">Class X (AISSE)</option>
                            <option value="12">Class XII (AISSCE)</option>
                        </select>
                    </div>
                    <div class="w-56">
                        <label class="form-label mb-1.5">Academic Year *</label>
                        <select v-model="searchYear" required class="field">
                            <option value="" disabled>Select academic year</option>
                            <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                                {{ ay.label }}{{ ay.status === 'active' ? ' (active)' : '' }}
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary text-sm">Search</button>
                </form>
            </div>

            <!-- Step 2: summary + toppers, one form -->
            <div v-if="selectedAcademicYear" class="card space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            {{ activeResult ? 'Edit' : 'Create' }} Board Result — {{ selectedAcademicYear }}
                            <span v-if="activeResult" class="text-xs px-2.5 py-0.5 rounded-full font-medium capitalize" :class="statusClass(activeResult.status)">
                                {{ activeResult.status }}
                            </span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Class {{ selectedClass ?? searchClass }} ({{ (selectedClass ?? searchClass) == 12 ? 'AISSCE' : 'AISSE' }})
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Link v-if="activeResult" :href="`/school-admin/${school.id}/board-results/${activeResult.id}/toppers`" class="btn-secondary text-xs">
                            Manage Toppers &amp; Subjects →
                        </Link>
                    </div>
                </div>

                <!-- Rejection Banner if rejected -->
                <div v-if="activeResult?.status === 'rejected'" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                    <p class="font-semibold text-red-800">Result Submission Rejected</p>
                    <p class="mt-1">{{ activeResult.rejection_reason || 'Please correct the details or re-upload the result PDF, then resubmit.' }}</p>
                </div>

                <form @submit.prevent="submit(false)" class="space-y-6" enctype="multipart/form-data">
                    <!-- SECTION 1: Aggregate Summary Stats -->
                    <div>
                        <h4 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2 text-indigo-700">1. Summary Statistics</h4>
                        <p class="text-xs text-gray-500 mb-3">Overall performance statistics for Class {{ selectedClass ?? searchClass }}. Pass % calculates automatically.</p>

                        <div class="grid sm:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label mb-1.5">Total Appeared</label>
                                <input v-model.number="form.total_appeared" type="number" min="0" class="field" placeholder="e.g. 120" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Passed</label>
                                <input v-model.number="form.pass_count" type="number" min="0" class="field" placeholder="e.g. 115" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Pass % (Calculated)</label>
                                <div class="relative">
                                    <input v-model.number="form.pass_percent" type="number" min="0" max="100" step="0.01" class="field pr-8" placeholder="e.g. 95.83" :disabled="!canEditActive">
                                    <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Distinctions</label>
                                <input v-model.number="form.distinctions" type="number" min="0" class="field" placeholder="0" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1.5">First class</label>
                                <input v-model.number="form.first_class" type="number" min="0" class="field" placeholder="0" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Total marks (out of)</label>
                                <input v-model.number="form.total_marks" type="number" min="1" class="field" placeholder="e.g. 500" :disabled="!canEditActive">
                                <p class="text-[11px] text-gray-400 mt-1">Common total for calculating percentage below.</p>
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Highest mark (%)</label>
                                <input v-model.number="form.highest_mark" type="number" min="0" max="100" step="0.01" class="field" placeholder="e.g. 98.4" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Average mark (%)</label>
                                <input v-model.number="form.average_mark" type="number" min="0" max="100" step="0.01" class="field" placeholder="e.g. 78.2" :disabled="!canEditActive">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="form-label mb-1.5">Remarks</label>
                                <textarea v-model="form.remarks" rows="2" class="field" placeholder="Optional notes for Sahodaya reviewers" :disabled="!canEditActive"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: PDF Upload & Attachments -->
                    <div class="border-t border-gray-100 pt-5">
                        <h4 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2 text-indigo-700">2. CBSE Result PDF &amp; Attachments</h4>
                        <p class="text-xs text-gray-500 mb-3">Upload official CBSE result tabulation sheet/PDF (required before submitting for verification).</p>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label mb-1.5">CBSE Result PDF *</label>
                                <div v-if="activeResult?.result_pdf_path" class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 mb-2 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                                        <span>✓ PDF Attached</span>
                                        <a :href="`/school-admin/${school.id}/board-results/${activeResult.id}/pdf`" target="_blank" class="underline text-indigo-600 font-normal">View PDF ↗</a>
                                    </div>
                                    <span class="text-[11px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-medium">Ready</span>
                                </div>
                                <input type="file" accept="application/pdf" class="field text-sm" :disabled="!canEditActive" @change="form.result_pdf = $event.target.files[0]">
                                <p class="text-[11px] text-gray-400 mt-1">Upload PDF file (max 20MB).</p>
                            </div>
                            <div>
                                <label class="form-label mb-1.5">Attachments (Word/Excel)</label>
                                <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" class="field text-sm" :disabled="!canEditActive"
                                       @change="form.attachments = Array.from($event.target.files || [])">
                                <p class="text-[11px] text-gray-400 mt-1">Optional additional sheets or summary docs.</p>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Toppers List -->
                    <div class="border-t border-gray-100 pt-5">
                        <h4 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2 text-indigo-700">3. Add Toppers</h4>
                        <p class="text-xs text-gray-500 mb-3">
                            Add toppers scored out of {{ form.total_marks || 500 }}. Percentage calculates automatically as you type.
                            <span v-if="activeResultContext?.isClass12">For Class XII, add stream &amp; subject-wise marks afterward via "Manage toppers &amp; subjects".</span>
                        </p>

                        <div class="overflow-x-auto -mx-2">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase text-gray-500 bg-slate-50/80">
                                    <tr>
                                        <th class="p-2">Student Name</th>
                                        <th class="p-2">CBSE Roll No</th>
                                        <th class="p-2">Marks Scored</th>
                                        <th class="p-2">%</th>
                                        <th class="p-2">Photo (Optional)</th>
                                        <th class="p-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in form.toppers" :key="i" class="border-t border-gray-100">
                                        <td class="p-2"><input v-model="row.name" type="text" placeholder="Student name" class="field text-sm" :disabled="!canEditActive"></td>
                                        <td class="p-2"><input v-model="row.roll_no" type="text" placeholder="CBSE Roll No" class="field text-sm w-36" :disabled="!canEditActive"></td>
                                        <td class="p-2"><input v-model.number="row.marks_obtained" type="number" min="0" :max="form.total_marks || undefined" placeholder="Marks" class="field text-sm w-28" :disabled="!canEditActive"></td>
                                        <td class="p-2 text-gray-600 font-semibold whitespace-nowrap">{{ rowPercentage(row) }}</td>
                                        <td class="p-2"><input type="file" accept="image/*" class="text-xs w-36" :disabled="!canEditActive" @change="row.photo = $event.target.files[0]"></td>
                                        <td class="p-2">
                                            <button v-if="canEditActive && form.toppers.length > 1" type="button" class="text-red-500 hover:underline text-xs" @click="removeRow(i)">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button v-if="canEditActive" type="button" class="btn-secondary text-xs mt-3" @click="addRow">+ Add Topper Row</button>
                    </div>

                    <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-600 space-y-0.5">
                        <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
                    </div>

                    <!-- FOOTER ACTIONS -->
                    <div class="border-t border-gray-100 pt-4 flex flex-wrap items-center justify-between gap-3">
                        <div v-if="canEditActive" class="flex flex-wrap items-center gap-3">
                            <!-- Button 1: Save Draft -->
                            <button type="button" @click="submit(false)" :disabled="form.processing || wouldExceedCap"
                                    class="btn-secondary text-sm px-4 py-2">
                                Save Draft
                            </button>

                            <!-- Button 2: Save & Submit for Verification -->
                            <button type="button" @click="submit(true)" :disabled="form.processing || wouldExceedCap"
                                    class="btn-primary text-white px-5 py-2 text-sm font-semibold shadow-sm">
                                Save &amp; Submit for Verification
                            </button>
                        </div>
                        <p v-else class="text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-md">
                            Result is {{ activeResult?.status }} — locked for editing.
                        </p>
                    </div>
                </form>

                <!-- Toppers already added for this result -->
                <div v-if="activeResult?.toppers?.length" class="mt-5 pt-4 border-t border-gray-100 divide-y divide-gray-50">
                    <h4 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2 text-indigo-700">Saved Toppers List ({{ activeResult.toppers.length }})</h4>
                    <div v-for="t in sortedActiveToppers" :key="t.id" class="py-2 flex items-center justify-between gap-3 text-sm">
                        <div>
                            <span class="font-semibold text-indigo-600">#{{ t.rank ?? '—' }}</span>
                            {{ t.name }}
                            <span class="text-xs text-gray-400 ml-1">{{ [t.admission_no, t.roll_no].filter(Boolean).join(' · ') }}</span>
                        </div>
                        <div class="text-xs text-gray-500 flex items-center gap-3">
                            <span class="font-semibold text-gray-700">{{ t.percentage }}%</span>
                            <span v-if="t.marks_obtained != null && t.total_marks != null">{{ t.marks_obtained }}/{{ t.total_marks }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Previously saved results -->
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wide">All saved results</h3>
                <div v-for="r in results" :key="r.id" class="card card--flush">
                    <div class="flex items-center justify-between px-5 py-4 bg-gray-50 border-b border-gray-100 gap-3 flex-wrap">
                        <div>
                            <span class="font-bold text-gray-800">
                                Class {{ r.class }} — {{ r.examination_type }} — {{ r.academic_year }}
                            </span>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full capitalize"
                                  :class="statusClass(r.status)">{{ r.status }}</span>
                            <div class="flex flex-wrap items-center gap-4 mt-1 text-xs text-gray-500">
                                <span>{{ r.total_appeared }} appeared</span>
                                <span>{{ r.pass_count }} passed</span>
                                <span class="font-semibold text-green-600">{{ r.pass_percent }}%</span>
                                <span v-if="r.highest_mark">High {{ r.highest_mark }}</span>
                                <span v-if="r.average_mark">Avg {{ r.average_mark }}</span>
                                <span v-if="r.distinctions">{{ r.distinctions }} distinctions</span>
                                <span v-if="r.result_pdf_path" class="text-indigo-600">PDF on file</span>
                                <span v-else class="text-amber-600">PDF missing</span>
                            </div>
                            <p v-if="r.rejection_reason" class="text-xs text-red-600 mt-1">{{ r.rejection_reason }}</p>
                            <p v-if="r.uploads?.length" class="text-xs text-gray-400 mt-1">
                                Upload history:
                                <span v-for="(u, i) in r.uploads" :key="u.id">
                                    {{ u.file_type }} v{{ u.version }}{{ i < r.uploads.length - 1 ? ', ' : '' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" @click="loadResult(r)" class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-blue-100 transition">
                                Edit here ↑
                            </button>
                            <Link :href="`/school-admin/${school.id}/board-results/${r.id}/toppers`"
                                  class="text-xs bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-indigo-100 transition">
                                {{ r.class == 12 ? 'Manage toppers & subjects' : 'Manage toppers' }} ({{ r.toppers?.length ?? 0 }})
                            </Link>
                            <button v-if="canSubmit(r)" type="button" @click="submitForReview(r)"
                                    class="text-xs bg-green-50 text-green-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-green-100">
                                Submit
                            </button>
                            <label v-if="isEditable(r)" class="text-xs bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg font-semibold cursor-pointer hover:bg-slate-100">
                                Upload PDF
                                <input type="file" accept="application/pdf" class="hidden" @change="uploadPdf(r, $event)">
                            </label>
                            <button v-if="isEditable(r)" @click="remove(r)" class="text-xs text-red-400 hover:underline">Delete</button>
                        </div>
                    </div>

                    <div v-if="r.toppers?.length" class="px-5 py-3 flex flex-wrap gap-3">
                        <div v-for="t in r.toppers.slice(0, 6)" :key="t.id"
                             class="flex items-center gap-2 text-xs text-gray-600">
                            <img v-if="t.photo" :src="t.photo" class="w-7 h-7 rounded-full object-cover border border-gray-100">
                            <span class="text-gray-400 w-7 h-7 rounded-full bg-indigo-50 flex items-center justify-center font-bold text-indigo-600" v-else>
                                {{ t.name[0] }}
                            </span>
                            <span>{{ t.name }} <span class="text-indigo-600 font-semibold">{{ t.percentage }}%</span></span>
                        </div>
                        <span v-if="r.toppers.length > 6" class="text-xs text-gray-400 self-center">
                            +{{ r.toppers.length - 6 }} more
                        </span>
                    </div>

                    <div v-if="r.subject_stats && Object.keys(r.subject_stats).length"
                         class="px-5 pb-4 border-t border-slate-50 pt-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Subject stats</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="(stat, subject) in r.subject_stats" :key="subject"
                                  class="text-xs px-2.5 py-1 rounded-lg bg-slate-50 text-slate-700 border border-slate-100">
                                {{ subject }}:
                                <span class="font-semibold text-indigo-700">{{ stat.top_score }}</span>
                                <span class="text-slate-400">({{ stat.topper_name }})</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!results.length"
                     class="card card--dashed p-10 text-center text-slate-400">
                    No board results added yet.
                </div>
            </div>

            <div class="card">
                <div class="mb-4 border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-semibold text-slate-800">Audit history</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Board result, topper, and achievement changes for this school.</p>
                </div>
                <div v-if="auditHistory?.length" class="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                    <div v-for="entry in auditHistory" :key="entry.id" class="py-3 text-sm">
                        <p class="font-medium text-slate-800">{{ entry.description }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            <span class="capitalize">{{ entry.action }}</span>
                            · {{ entry.log_name }}
                            · {{ formatAuditTime(entry.created_at) }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400 py-6 text-center">No audit entries for board results yet.</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    school: Object,
    results: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    auditHistory: { type: Array, default: () => [] },
    topperCap: { type: Number, default: 5 },
    selectedClass: { type: Number, default: null },
    academicYearOptions: { type: Array, default: () => [] },
    selectedAcademicYear: { type: String, default: null },
    activeResult: { type: Object, default: null },
    activeResultContext: { type: Object, default: null },
});

const pageTitle = computed(() => {
    if (props.selectedClass === 12) return 'Class XII Board Results';
    if (props.selectedClass === 10) return 'Class X Board Results';
    return 'Board Results';
});

const pageDescription = computed(() => {
    if (props.selectedClass === 12) return 'Search or start a year, enter the summary, then add toppers — all on this page.';
    if (props.selectedClass === 10) return 'Search or start a year, enter the summary, then add toppers — all on this page.';
    return 'Enter CBSE AISSE/AISSCE summaries, upload the official PDF, add toppers, then submit for Sahodaya verification.';
});

function formatAuditTime(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString(); } catch { return iso; }
}

// ── Step 1: search ──────────────────────────────────────────────────────
const searchYear = ref(props.selectedAcademicYear ?? '');
const searchClass = ref(props.selectedClass ? String(props.selectedClass) : '10');

function search() {
    router.get(`/school-admin/${props.school.id}/board-results`, {
        class: props.selectedClass ?? searchClass.value,
        academic_year: searchYear.value,
    }, { preserveState: true, preserveScroll: true });
}

function loadResult(r) {
    router.get(`/school-admin/${props.school.id}/board-results`, {
        class: r.class,
        academic_year: r.academic_year,
    }, { preserveScroll: true });
}

// ── Step 2: combined summary + toppers form ──────────────────────────────
const canEditActive = computed(() => !props.activeResult || ['draft', 'rejected'].includes(props.activeResult.status));

function blankRow() {
    return { name: '', roll_no: '', marks_obtained: '', photo: null };
}

function resultToFormData(r) {
    return {
        class: props.selectedClass ? String(props.selectedClass) : searchClass.value,
        academic_year: props.selectedAcademicYear ?? '',
        total_appeared: r?.total_appeared ?? '',
        pass_count: r?.pass_count ?? '',
        pass_percent: r?.pass_percent ?? '',
        distinctions: r?.distinctions ?? '',
        first_class: r?.first_class ?? '',
        highest_mark: r?.highest_mark ?? '',
        average_mark: r?.average_mark ?? '',
        total_marks: r?.total_marks ?? '',
        remarks: r?.remarks ?? '',
        result_pdf: null,
        attachments: [],
        toppers: [blankRow()],
    };
}

const form = useForm(resultToFormData(props.activeResult));

watch(() => [props.activeResult, props.selectedAcademicYear], () => {
    Object.assign(form, resultToFormData(props.activeResult));
    form.clearErrors();
});

// Auto-calculate Pass % when total_appeared & pass_count are entered
watch(() => [form.total_appeared, form.pass_count], ([appeared, passed]) => {
    if (appeared && Number(appeared) > 0 && passed !== '' && passed != null) {
        const calculated = Math.round((Number(passed) / Number(appeared)) * 10000) / 100;
        if (calculated >= 0 && calculated <= 100) {
            form.pass_percent = calculated;
        }
    }
});

// Auto-suggest highest mark from toppers if blank
watch(() => form.toppers, (rows) => {
    if (form.highest_mark === '' || form.highest_mark == null) {
        const percentages = rows
            .filter((r) => r.marks_obtained !== '' && r.marks_obtained != null && form.total_marks)
            .map((r) => Math.round((r.marks_obtained / form.total_marks) * 10000) / 100);
        if (percentages.length > 0) {
            const maxPerc = Math.max(...percentages);
            if (maxPerc > 0) {
                form.highest_mark = maxPerc;
            }
        }
    }
}, { deep: true });

const sortedActiveToppers = computed(() =>
    [...(props.activeResult?.toppers ?? [])].sort((a, b) => (a.rank ?? 999) - (b.rank ?? 999)),
);

const wouldExceedCap = computed(() => {
    const cap = props.activeResultContext?.topperCap;
    const count = props.activeResultContext?.topperCount ?? 0;
    const incoming = form.toppers.filter((row) => row.name || row.marks_obtained !== '').length;
    return cap != null && (count + incoming) > cap;
});

function addRow() {
    form.toppers.push(blankRow());
}

function removeRow(i) {
    form.toppers.splice(i, 1);
}

function rowPercentage(row) {
    if (!form.total_marks || row.marks_obtained === '' || row.marks_obtained == null) return '—';
    const val = Math.round(((row.marks_obtained / form.total_marks) * 100) * 100) / 100;
    return `${val}%`;
}

function submit(submitForReview = false) {
    if (submitForReview && !props.activeResult?.result_pdf_path && !form.result_pdf) {
        alert('Please select/upload the CBSE Result PDF before submitting for verification.');
        return;
    }

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.toppers = [blankRow()];
        },
    };

    const payload = {
        ...form.data(),
        submit_for_review: submitForReview ? 1 : 0,
    };

    if (props.activeResult?.id) {
        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}`, {
            ...payload,
            _method: 'put',
        }, options);
    } else {
        router.post(`/school-admin/${props.school.id}/board-results`, payload, options);
    }
}

// ── Historical results list actions ─────────────────────────────────────
function isEditable(r) {
    return r.status === 'draft' || r.status === 'rejected';
}

function canSubmit(r) {
    return isEditable(r) && !!r.result_pdf_path;
}

function submitForReview(r) {
    if (!confirm(`Submit Class ${r.class} (${r.academic_year}) for Sahodaya verification?`)) return;
    router.post(`/school-admin/${props.school.id}/board-results/${r.id}/submit`);
}

function uploadPdf(r, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const data = new FormData();
    data.append('result_pdf', file);
    router.post(`/school-admin/${props.school.id}/board-results/${r.id}/upload-pdf`, data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { event.target.value = ''; },
    });
}

function remove(r) {
    if (!confirm(`Delete Class ${r.class} results for ${r.academic_year}?`)) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${r.id}`);
}

function statusClass(status) {
    const map = {
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-amber-50 text-amber-700',
        verified: 'bg-blue-50 text-blue-700',
        approved: 'bg-indigo-50 text-indigo-700',
        published: 'bg-green-50 text-green-700',
        rejected: 'bg-red-50 text-red-700',
    };
    return map[status] || 'bg-slate-100 text-slate-600';
}
</script>
