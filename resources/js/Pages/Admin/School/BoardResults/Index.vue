<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <!-- TOP SEGMENTED NAVIGATION (CLASS X vs CLASS XII) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Board Examination Results
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Manage Class X (AISSE) &amp; Class XII (AISSCE) board results, upload proof documents, and submit for Sahodaya verification.
                </p>
            </div>

            <div class="flex items-center gap-3 self-start md:self-auto print:hidden">
                <button
                    type="button"
                    @click="printReport"
                    class="btn-secondary text-xs py-1.5 font-bold flex items-center gap-1.5"
                >
                    <span>🖨</span> Print Rank Report
                </button>

                <!-- Class segmented switch -->
                <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200/80">
                    <Link
                        :href="`/school-admin/${school.id}/board-results?class=10`"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                        :class="selectedClass === 10 ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        Class X (AISSE)
                    </Link>
                    <Link
                        :href="`/school-admin/${school.id}/board-results?class=12`"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                        :class="selectedClass === 12 ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        Class XII (AISSCE)
                    </Link>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- WORKING YEAR SELECTOR BAR -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                        {{ selectedClass === 12 ? '12' : '10' }}
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Selected Examination</p>
                        <p class="text-sm font-bold text-gray-800">
                            Class {{ selectedClass ?? searchClass }} ({{ (selectedClass ?? searchClass) == 12 ? 'AISSCE' : 'AISSE' }})
                        </p>
                    </div>
                </div>

                <form @submit.prevent="search" class="flex items-center gap-3 flex-wrap">
                    <div v-if="(selectedClass ?? searchClass) == 12" class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-gray-600 whitespace-nowrap">Stream:</label>
                        <select v-model="searchStream" class="field text-xs py-1.5 w-36 font-semibold bg-white">
                            <option value="science">Science</option>
                            <option value="commerce">Commerce</option>
                            <option value="humanities">Humanities</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-gray-600 whitespace-nowrap">Academic Year:</label>
                        <select v-model="searchYear" required class="field text-xs py-1.5 w-48 font-medium">
                            <option value="" disabled>Select Academic Year</option>
                            <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                                {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary text-xs px-4 py-1.5 font-semibold">
                        Search
                    </button>
                </form>
            </div>

            <!-- MAIN WORKSPACE CARD -->
            <div v-if="selectedAcademicYear" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
                <!-- Workspace Title Bar -->
                <div class="p-5 bg-gradient-to-r from-slate-50 to-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-gray-900">
                                {{ activeResult ? 'Edit' : 'Create' }} Result — {{ selectedAcademicYear }}
                            </h2>
                            <span v-if="activeResult" class="text-xs px-2.5 py-0.5 rounded-full font-semibold capitalize border" :class="statusClass(activeResult.status)">
                                {{ activeResult.status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Fill in aggregate performance data and toppers below.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="activeResult" :href="`/school-admin/${school.id}/board-results/${activeResult.id}/toppers`" class="btn-primary text-xs px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold flex items-center gap-1.5 shadow-sm border-none">
                            <span>🎯</span> Manage Stream &amp; Subject-Wise Toppers →
                        </Link>
                    </div>
                </div>

                <!-- Rejection Banner if rejected -->
                <div v-if="activeResult?.status === 'rejected'" class="p-4 bg-red-50 border-l-4 border-red-500 text-xs text-red-700">
                    <p class="font-bold text-red-800">Result Submission Rejected by Sahodaya</p>
                    <p class="mt-1">{{ activeResult.rejection_reason || 'Please review and update the summary or proof document, then resubmit for verification.' }}</p>
                </div>

                <form @submit.prevent="submit(false)" class="p-6 space-y-8" enctype="multipart/form-data">
                    <!-- SECTION 1: Aggregate Summary Stats -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">1</span>
                            <h3 class="font-bold text-gray-800 text-sm">Summary Performance Statistics</h3>
                        </div>

                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="form-label mb-1">Total Appeared (Optional)</label>
                                <input v-model.number="form.total_appeared" type="number" min="0" class="field text-sm" placeholder="e.g. 120" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Total Passed (Optional)</label>
                                <input v-model.number="form.pass_count" type="number" min="0" class="field text-sm" placeholder="e.g. 115" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1 font-semibold text-gray-700">Pass % (Calculated)</label>
                                <div class="relative">
                                    <input v-model="form.pass_percent" type="number" step="0.01" min="0" max="100" class="field text-sm font-bold text-emerald-700 bg-emerald-50/40" placeholder="e.g. 95.83" :disabled="!canEditActive">
                                    <span class="absolute right-3 top-2.5 text-xs text-emerald-600 font-bold">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label mb-1 font-semibold text-gray-800">Total Marks (Out of)</label>
                                <div v-if="!isXii" class="field text-sm font-bold text-indigo-700 bg-indigo-50/40 flex items-center border-indigo-100">
                                    {{ classXTotal }}
                                    <span class="ml-1.5 text-[10px] font-normal text-gray-400">(admin-locked)</span>
                                </div>
                                <p v-else class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 leading-snug">
                                    Locked per stream — pick each topper's stream in the table below.
                                </p>
                            </div>

                            <div>
                                <label class="form-label mb-1">Distinctions Count</label>
                                <input v-model.number="form.distinctions" type="number" min="0" class="field text-sm" placeholder="0" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">First Class Count</label>
                                <input v-model.number="form.first_class" type="number" min="0" class="field text-sm" placeholder="0" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Highest Mark (%)</label>
                                <input v-model.number="form.highest_mark" type="number" step="0.01" min="0" max="100" class="field text-sm" placeholder="e.g. 98.4" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Average Mark (%)</label>
                                <input v-model.number="form.average_mark" type="number" step="0.01" min="0" max="100" class="field text-sm" placeholder="e.g. 78.2" :disabled="!canEditActive">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label mb-1">School Remarks / Notes</label>
                            <textarea v-model="form.remarks" rows="2" class="field text-sm" placeholder="Optional notes for Sahodaya reviewers" :disabled="!canEditActive"></textarea>
                        </div>
                    </div>

                    <!-- SECTION 2: Proof Uploads -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">2</span>
                            <h3 class="font-bold text-gray-800 text-sm">Proof Document (PDF / Image)</h3>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label mb-1">CBSE Tabulation Sheet / Proof Document (Required for verification)</label>
                                <div v-if="activeResult?.result_pdf_path" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 mb-2 flex items-center justify-between shadow-xs">
                                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                                        <span>✓ Proof Attached</span>
                                        <a :href="`/school-admin/${school.id}/board-results/${activeResult.id}/pdf`" target="_blank" class="underline text-indigo-600 hover:text-indigo-800 font-normal">View Attached File ↗</a>
                                    </div>
                                    <span class="text-[11px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-medium">Ready</span>
                                </div>
                                <input type="file" accept="application/pdf,image/png,image/jpeg,image/jpg,image/webp" class="field text-sm bg-white" :disabled="!canEditActive" @change="form.result_pdf = $event.target.files[0]">
                                <p class="text-[11px] text-gray-400 mt-1">Accepts PDF, JPG, PNG, WEBP files up to 20MB.</p>
                            </div>
                            <div>
                                <label class="form-label mb-1">Additional Attachments (Word/Excel/Images)</label>
                                <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,image/png,image/jpeg,image/jpg,image/webp" class="field text-sm bg-white" :disabled="!canEditActive"
                                       @change="form.attachments = Array.from($event.target.files || [])">
                                <p class="text-[11px] text-gray-400 mt-1">Optional additional sheets or summary docs.</p>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: School Toppers -->
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">3</span>
                                <h3 class="font-bold text-gray-800 text-sm">School Toppers</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link v-if="(selectedClass ?? searchClass) == 12" :href="`/school-admin/${school.id}/board-results/subject-toppers`"
                                      class="btn-primary text-xs px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold flex items-center gap-1.5 shadow-sm border-none">
                                    <span>🎯</span> Open Subject-Wise Toppers Page →
                                </Link>
                                <span class="text-xs text-gray-500">{{ isXii ? 'Out of marks — set by stream' : `Out of ${classXTotal} marks` }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                            📌 Enter your top students here. Also add <strong>every</strong> student scoring 90% or above — they will be included in the Sahodaya Board Results reports and 90%+ Achievers list.
                        </p>

                        <div v-if="isXii" class="flex flex-wrap items-center justify-between gap-3 bg-indigo-50/50 p-2.5 rounded-xl border border-indigo-100 mb-3">
                            <span class="text-xs font-semibold text-slate-700">⚡ Quick Stream Fill for All Rows:</span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button v-for="(label, key) in streamOptions" :key="key" type="button"
                                        @click="setStreamForAll(key)"
                                        class="btn-secondary text-[11px] py-1 px-2.5 !bg-white hover:!bg-indigo-50 border-slate-200">
                                    Set all to {{ label }}
                                </button>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="p-3">Student Name</th>
                                        <th class="p-3">Gender</th>
                                        <th v-if="isXii" class="p-3">Stream</th>
                                        <th class="p-3">CBSE Roll No</th>
                                        <th class="p-3">Marks Scored</th>
                                        <th class="p-3">%</th>
                                        <th class="p-3">Photo (Optional)</th>
                                        <th class="p-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(row, i) in form.toppers" :key="i" class="hover:bg-slate-50/50">
                                        <td class="p-3"><input v-model="row.name" type="text" placeholder="Student name" class="field text-sm" :disabled="!canEditActive"></td>
                                        <td class="p-3">
                                            <select v-model="row.gender" class="field text-sm w-28" :disabled="!canEditActive">
                                                <option value="">— Select —</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </td>
                                        <td v-if="isXii" class="p-3">
                                            <select v-model="row.stream_key" class="field text-sm w-32" :disabled="!canEditActive">
                                                <option value="">— Select —</option>
                                                <option v-for="(label, key) in streamOptions" :key="key" :value="key">{{ label }}</option>
                                            </select>
                                            <p class="text-[10px] text-gray-400 mt-0.5" v-if="row.stream_key">Out of {{ rowTotalMarks(row) ?? '—' }}</p>
                                        </td>
                                        <td class="p-3"><input v-model="row.roll_no" type="text" placeholder="CBSE Roll No" class="field text-sm w-36" :disabled="!canEditActive"></td>
                                        <td class="p-3"><input v-model.number="row.marks_obtained" type="number" min="0" :max="rowTotalMarks(row) || undefined" placeholder="Marks" class="field text-sm w-28" :disabled="!canEditActive"></td>
                                        <td class="p-3 text-indigo-600 font-bold whitespace-nowrap">{{ rowPercentage(row) }}</td>
                                        <td class="p-3"><input type="file" accept="image/*" class="text-xs w-40" :disabled="!canEditActive" @change="row.photo = $event.target.files[0]"></td>
                                        <td class="p-3 text-right">
                                            <button v-if="canEditActive && form.toppers.length > 1" type="button" class="text-red-500 hover:text-red-700 text-xs font-semibold" @click="removeRow(i)">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button v-if="canEditActive" type="button" class="btn-secondary text-xs mt-3 px-3 py-1.5 font-semibold" @click="addRow">+ Add Topper Row</button>
                    </div>

                    <!-- Errors alert -->
                    <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-600 space-y-1">
                        <p class="font-bold text-red-800">Please review the following errors:</p>
                        <p v-for="(msg, key) in form.errors" :key="key">• {{ msg }}</p>
                    </div>

                    <!-- FOOTER ACTION TOOLBAR -->
                    <div class="border-t border-gray-100 pt-5 flex flex-wrap items-center justify-between gap-4">
                        <div v-if="canEditActive" class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="submit(false)" :disabled="form.processing"
                                    class="btn-secondary text-sm px-5 py-2.5 font-semibold">
                                Save Draft
                            </button>
                            <button type="button" @click="submit(true)" :disabled="form.processing"
                                    class="btn-primary text-sm px-6 py-2.5 font-bold shadow-md bg-emerald-600 hover:bg-emerald-700 border-none">
                                Save &amp; Submit for Verification
                            </button>
                        </div>
                        <div v-else class="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200">
                            This result is {{ activeResult?.status }} and locked from editing.
                        </div>
                    </div>
                </form>
            </div>

            <div v-else class="p-12 text-center text-gray-400 text-sm card bg-white">
                Select an academic year above and click "Search" to begin.
            </div>

            <!-- SAVED RESULTS HISTORY TABLE -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Saved Results History</h3>
                        <p class="text-xs text-gray-500 mt-0.5">All saved board results for Class {{ selectedClass ?? searchClass }}.</p>
                    </div>
                </div>

                <div v-if="results.length" class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="p-3">Academic Year</th>
                                <th class="p-3">Class</th>
                                <th class="p-3">Appeared</th>
                                <th class="p-3">Passed</th>
                                <th class="p-3">Pass %</th>
                                <th class="p-3">Highest %</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="r in results" :key="r.id" class="hover:bg-slate-50/60 transition">
                                <td class="p-3 font-bold text-gray-900">{{ r.academic_year }}</td>
                                <td class="p-3">Class {{ r.class }}</td>
                                <td class="p-3">{{ r.total_appeared }}</td>
                                <td class="p-3">{{ r.pass_count }}</td>
                                <td class="p-3 font-bold text-emerald-600">{{ r.pass_percent }}%</td>
                                <td class="p-3 font-bold text-indigo-600">{{ r.highest_mark ? `${r.highest_mark}%` : '—' }}</td>
                                <td class="p-3">
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold capitalize border" :class="statusClass(r.status)">
                                        {{ r.status }}
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <button type="button" @click="loadResult(r)" class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Open Result →
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-8 text-center text-gray-400 text-xs">
                    No saved board results recorded yet for Class {{ selectedClass ?? searchClass }}.
                </div>
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
    streamOptions: { type: Object, default: () => ({}) },
    activeResult: { type: Object, default: null },
    activeResultContext: { type: Object, default: null },
    marksConfig: { type: Object, default: () => ({ classX: 500, byStream: {} }) },
});

const pageTitle = computed(() => {
    if (props.selectedClass === 12) return 'Class XII Board Results';
    if (props.selectedClass === 10) return 'Class X Board Results';
    return 'Board Results';
});

// ── Step 1: search ──────────────────────────────────────────────────────
const searchYear = ref(props.selectedAcademicYear ?? '');
const searchClass = ref(props.selectedClass ? String(props.selectedClass) : '10');
const searchStream = ref('science');

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

// Admin-locked "out of" marks — schools no longer type this in. Class X is one shared
// value; Class XII varies per stream, so each topper row resolves its own total once a
// stream is picked (see marksConfig.byStream, keyed by the same keys as streamOptions).
const isXii = computed(() => Number(props.selectedClass ?? searchClass.value) === 12);
const classXTotal = computed(() => props.marksConfig?.classX ?? 500);

function rowTotalMarks(row) {
    if (isXii.value) {
        return row.stream_key ? (props.marksConfig?.byStream?.[row.stream_key] ?? null) : null;
    }
    return classXTotal.value;
}

function resolveStreamKey(raw) {
    if (!raw) return searchStream.value || 'science';
    const lower = String(raw).toLowerCase().trim();
    for (const [key, label] of Object.entries(props.streamOptions ?? {})) {
        if (key.toLowerCase() === lower || String(label).toLowerCase() === lower) {
            return key;
        }
    }
    return raw;
}

function blankRow() {
    return { name: '', gender: '', stream_key: searchStream.value || 'science', roll_no: '', marks_obtained: '', photo: null };
}

function resultToFormData(r) {
    const loadedToppers = (r?.toppers && r.toppers.length > 0)
        ? r.toppers.map(t => ({
            id: t.id,
            name: t.name || '',
            gender: t.gender || '',
            stream_key: resolveStreamKey(t.stream || (t.exam_stream?.slug)),
            roll_no: t.roll_no || '',
            marks_obtained: t.marks_obtained ?? t.total_marks ?? '',
            photo: null,
        }))
        : [blankRow()];

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
        total_marks: r?.total_marks || props.marksConfig?.classX || 500,
        remarks: r?.remarks ?? '',
        result_pdf: null,
        attachments: [],
        toppers: loadedToppers,
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
            .filter((r) => r.marks_obtained !== '' && r.marks_obtained != null && rowTotalMarks(r))
            .map((r) => Math.round((r.marks_obtained / rowTotalMarks(r)) * 10000) / 100);
        if (percentages.length > 0) {
            const maxPerc = Math.max(...percentages);
            if (maxPerc > 0) {
                form.highest_mark = maxPerc;
            }
        }
    }
}, { deep: true });

// Prefer the per-result cap (correctly scoped to this result's own class) over the
// page-load default, which previously could reflect the wrong class's quota.
const effectiveTopperCap = computed(() => props.activeResultContext?.topperCap ?? props.topperCap);

const wouldExceedCap = computed(() => {
    if (!effectiveTopperCap.value) return false;
    const existingCount = props.activeResult?.toppers?.length ?? 0;
    const validNew = form.toppers.filter((r) => r.name && r.marks_obtained !== '').length;
    return (existingCount + validNew) > effectiveTopperCap.value;
});

function addRow() {
    const lastStream = form.toppers.length > 0
        ? (form.toppers[form.toppers.length - 1].stream_key || searchStream.value || '')
        : (searchStream.value || '');
    form.toppers.push({ name: '', gender: '', stream_key: lastStream, roll_no: '', marks_obtained: '', photo: null });
}

function setStreamForAll(streamKey) {
    if (!streamKey) return;
    form.toppers.forEach(row => {
        row.stream_key = streamKey;
    });
}

function removeRow(i) {
    form.toppers.splice(i, 1);
}

function rowPercentage(row) {
    const total = rowTotalMarks(row);
    if (!total || row.marks_obtained === '' || row.marks_obtained == null) return '—';
    const val = Math.round(((row.marks_obtained / total) * 100) * 100) / 100;
    return `${val}%`;
}

function statusClass(s) {
    switch (s) {
        case 'verified': case 'approved': case 'published':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'submitted':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'rejected':
            return 'bg-red-50 text-red-700 border-red-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}

function submit(submitForReview) {
    const payload = {
        ...form.data(),
        submit_for_review: submitForReview,
    };

    if (props.activeResult) {
        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}`, {
            ...payload,
            _method: 'put',
        }, { forceFormData: true });
    } else {
        router.post(`/school-admin/${props.school.id}/board-results`, payload, {
            forceFormData: true,
        });
    }
}

// ── Step 3: Class XII Embedded Subject-Wise Entry ──────────────────────────
const section3Tab = ref('overall');
const selectedSubjectOption = ref('');
const customSubjectInput = ref('');
const subjectForm = ref({ name: '', roll_no: '', marks: '' });

const default23Subjects = [
    'English core', 'Hindi core', 'Hindi elective', 'Malayalam', 'Sanskrit',
    'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer science',
    'Psychology', 'Informatics practices', 'History', 'Sociology',
    'Political science', 'Economics', 'Accountancy', 'Business Studies',
    'Home science', 'Fashion studies', 'Physical education', 'Business administration', 'KTPI'
];

const masterSubjects = computed(() =>
    props.activeResultContext?.standardSubjects?.length
        ? props.activeResultContext.standardSubjects
        : default23Subjects
);

const subjectWiseLeaders = computed(() =>
    props.activeResultContext?.subjectWiseLeaders ?? []
);

function saveSubjectTopper() {
    const finalSubject = selectedSubjectOption.value === '__custom__'
        ? customSubjectInput.value.trim()
        : selectedSubjectOption.value;

    if (!finalSubject || !subjectForm.value.name || subjectForm.value.marks === '') return;

    if (!props.activeResult) {
        alert('Please click "Save Draft" first to initialize this board result before adding subject toppers.');
        return;
    }

    const existing = (props.activeResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === subjectForm.value.name.toLowerCase()
    );

    if (existing) {
        const currentSubjectMarks = { ...(existing.subject_marks ?? {}) };
        currentSubjectMarks[finalSubject] = subjectForm.value.marks;

        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}/toppers/${existing.id}`, {
            ...existing,
            _method: 'put',
            subject_marks: currentSubjectMarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSubjectOption.value = '';
                customSubjectInput.value = '';
                subjectForm.value = { name: '', roll_no: '', marks: '' };
            },
        });
    } else {
        const subjectMarks = {};
        subjectMarks[finalSubject] = subjectForm.value.marks;

        router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}/toppers/single`, {
            name: subjectForm.value.name,
            roll_no: subjectForm.value.roll_no,
            percentage: subjectForm.value.marks,
            marks_obtained: subjectForm.value.marks,
            total_marks: 100,
            subject_marks: subjectMarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSubjectOption.value = '';
                customSubjectInput.value = '';
                subjectForm.value = { name: '', roll_no: '', marks: '' };
            },
        });
    }
}

function removeSubjectTopper(row) {
    if (!confirm(`Remove subject topper "${row.name}" for ${row.subject}?`)) return;

    const existing = (props.activeResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === row.name.toLowerCase()
    );

    if (!existing) return;

    const updatedSubjectMarks = { ...(existing.subject_marks ?? {}) };
    delete updatedSubjectMarks[row.subject];

    router.post(`/school-admin/${props.school.id}/board-results/${props.activeResult.id}/toppers/${existing.id}`, {
        ...existing,
        _method: 'put',
        subject_marks: updatedSubjectMarks,
    }, {
        preserveScroll: true,
    });
}

function printReport() {
    window.print();
}
</script>
