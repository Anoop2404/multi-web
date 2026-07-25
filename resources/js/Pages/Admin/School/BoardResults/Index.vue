<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <!-- TOP TOOLBAR & HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Academic Management</span>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Board Examination Results</h1>
                <p class="text-xs text-gray-500 mt-1">Manage Class X (AISSE) &amp; Class XII (AISSCE) board results, upload proof documents, and submit for Sahodaya verification.</p>
            </div>

            <!-- Class Switcher Tabs -->
            <div class="flex items-center bg-gray-100 p-1 rounded-xl shadow-inner border border-gray-200 self-start md:self-auto">
                <Link
                    :href="`/school-admin/${school.id}/board-results?class=10`"
                    class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                    :class="(selectedClass === 10 || (!selectedClass && searchClass === '10')) ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
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
                        Load Result
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
                        <Link v-if="activeResult" :href="`/school-admin/${school.id}/board-results/${activeResult.id}/toppers`" class="btn-secondary text-xs">
                            Manage Toppers &amp; Subjects →
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
                                <label class="form-label mb-1">Total Appeared *</label>
                                <input v-model.number="form.total_appeared" type="number" min="0" class="field text-sm" placeholder="e.g. 120" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Total Passed *</label>
                                <input v-model.number="form.pass_count" type="number" min="0" class="field text-sm" placeholder="e.g. 115" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Pass % (Calculated)</label>
                                <div class="relative">
                                    <input v-model.number="form.pass_percent" type="number" min="0" max="100" step="0.01" class="field text-sm pr-8 font-semibold text-emerald-700 bg-emerald-50/30" placeholder="e.g. 95.83" :disabled="!canEditActive">
                                    <span class="absolute right-3 top-2.5 text-xs text-emerald-600 font-bold">%</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label mb-1">Total Marks (Common Out of)</label>
                                <input v-model.number="form.total_marks" type="number" min="1" class="field text-sm" placeholder="e.g. 500" :disabled="!canEditActive">
                                <p class="text-[11px] text-gray-400 mt-1">Used for topper percentages.</p>
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
                                <input v-model.number="form.highest_mark" type="number" min="0" max="100" step="0.01" class="field text-sm" placeholder="e.g. 98.4" :disabled="!canEditActive">
                            </div>
                            <div>
                                <label class="form-label mb-1">Average Mark (%)</label>
                                <input v-model.number="form.average_mark" type="number" min="0" max="100" step="0.01" class="field text-sm" placeholder="e.g. 78.2" :disabled="!canEditActive">
                            </div>
                            <div class="sm:col-span-2 lg:col-span-4">
                                <label class="form-label mb-1">School Remarks / Notes</label>
                                <textarea v-model="form.remarks" rows="2" class="field text-sm" placeholder="Optional notes for Sahodaya reviewers" :disabled="!canEditActive"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Proof Document (PDF or Image) -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">2</span>
                            <h3 class="font-bold text-gray-800 text-sm">CBSE Result Document (PDF / Image Proof)</h3>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4 bg-slate-50/70 p-4 rounded-xl border border-slate-200/80">
                            <div>
                                <label class="form-label mb-1 font-semibold">CBSE Tabulation Sheet / Proof Document *</label>
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
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">3</span>
                                <h3 class="font-bold text-gray-800 text-sm">School Toppers</h3>
                            </div>
                            <span class="text-xs text-gray-500">Out of {{ form.total_marks || 500 }} marks</span>
                        </div>

                        <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="p-3">Student Name</th>
                                        <th v-if="(selectedClass ?? searchClass) == 12" class="p-3">Stream *</th>
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
                                        <td v-if="(selectedClass ?? searchClass) == 12" class="p-3">
                                            <select v-model="row.stream_key" required class="field text-sm w-36" :disabled="!canEditActive">
                                                <option value="" disabled>Select Stream</option>
                                                <option value="science">Science</option>
                                                <option value="commerce">Commerce</option>
                                                <option value="humanities">Humanities</option>
                                            </select>
                                        </td>
                                        <td class="p-3"><input v-model="row.roll_no" type="text" placeholder="CBSE Roll No" class="field text-sm w-36" :disabled="!canEditActive"></td>
                                        <td class="p-3"><input v-model.number="row.marks_obtained" type="number" min="0" :max="form.total_marks || undefined" placeholder="Marks" class="field text-sm w-28" :disabled="!canEditActive"></td>
                                        <td class="p-3 text-indigo-600 font-bold whitespace-nowrap">{{ rowPercentage(row) }}</td>
                                        <td class="p-3"><input type="file" accept="image/*" class="text-xs w-40" :disabled="!canEditActive" @change="row.photo = $event.target.files[0]"></td>
                                        <td class="p-3 text-right">
                                            <button v-if="canEditActive && form.toppers.length > 1" type="button" class="text-red-500 hover:text-red-700 text-xs font-semibold" @click="removeRow(i)">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button v-if="canEditActive" type="button" class="btn-secondary text-xs mt-3 px-3 py-1.5" @click="addRow">+ Add Topper Row</button>
                    </div>

                    <!-- Errors alert -->
                    <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-600 space-y-1">
                        <p class="font-bold text-red-800">Please review the following errors:</p>
                        <p v-for="(msg, key) in form.errors" :key="key">• {{ msg }}</p>
                    </div>

                    <!-- FOOTER ACTION TOOLBAR -->
                    <div class="border-t border-gray-100 pt-5 flex flex-wrap items-center justify-between gap-4">
                        <div v-if="canEditActive" class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="submit(false)" :disabled="form.processing || wouldExceedCap"
                                    class="btn-secondary text-sm px-5 py-2.5 font-semibold">
                                Save Draft
                            </button>

                            <button type="button" @click="submit(true)" :disabled="form.processing || wouldExceedCap"
                                    class="btn-primary text-white px-6 py-2.5 text-sm font-bold shadow-md bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 border-none transition-all">
                                Save &amp; Submit for Verification
                            </button>
                        </div>
                        <p v-else class="text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 px-4 py-2 rounded-xl">
                            Result status is {{ activeResult?.status }} — locked for editing.
                        </p>
                    </div>
                </form>
            </div>

            <!-- ALL SAVED RESULTS HISTORY TABLE -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Saved Results History</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Overview of all saved and submitted board examination results for your school.</p>
                    </div>
                </div>

                <div v-if="results.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-slate-50 border-b border-gray-200">
                            <tr>
                                <th class="p-3">Academic Year</th>
                                <th class="p-3">Class / Exam</th>
                                <th class="p-3">Appeared / Passed</th>
                                <th class="p-3">Pass %</th>
                                <th class="p-3">Proof Document</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="r in results" :key="r.id" class="hover:bg-slate-50/50">
                                <td class="p-3 font-bold text-gray-900">{{ r.academic_year }}</td>
                                <td class="p-3 text-gray-700">Class {{ r.class }} ({{ r.examination_type }})</td>
                                <td class="p-3 text-gray-600">{{ r.total_appeared }} appeared · {{ r.pass_count }} passed</td>
                                <td class="p-3 font-bold text-emerald-600">{{ r.pass_percent }}%</td>
                                <td class="p-3">
                                    <a v-if="r.result_pdf_path" :href="`/school-admin/${school.id}/board-results/${r.id}/pdf`" target="_blank" class="text-xs font-semibold text-indigo-600 hover:underline flex items-center gap-1">
                                        <span>View Document</span> ↗
                                    </a>
                                    <span v-else class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded">Missing Proof</span>
                                </td>
                                <td class="p-3">
                                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold capitalize border" :class="statusClass(r.status)">
                                        {{ r.status }}
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" @click="loadResult(r)" class="text-xs bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-indigo-100 transition">
                                            Edit Workspace ↑
                                        </button>
                                        <Link :href="`/school-admin/${school.id}/board-results/${r.id}/toppers`" class="text-xs bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-slate-200 transition">
                                            Toppers ({{ r.toppers?.length ?? 0 }})
                                        </Link>
                                        <button v-if="isEditable(r)" @click="remove(r)" class="text-xs text-red-500 hover:underline font-semibold ml-1">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-10 text-center text-gray-400 text-xs">
                    No board results recorded yet. Select an Academic Year above to begin.
                </div>
            </div>

            <!-- AUDIT HISTORY -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <div class="mb-3 border-b border-gray-100 pb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Audit &amp; Change Log</h3>
                </div>
                <div v-if="auditHistory?.length" class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
                    <div v-for="entry in auditHistory" :key="entry.id" class="py-2.5 text-xs">
                        <p class="font-semibold text-gray-800">{{ entry.description }}</p>
                        <p class="text-gray-400 mt-0.5">
                            <span class="capitalize font-medium text-gray-600">{{ entry.action }}</span> · {{ formatAuditTime(entry.created_at) }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-xs text-gray-400 py-4 text-center">No recent audit log entries.</p>
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
    return { name: '', stream_key: '', roll_no: '', marks_obtained: '', photo: null };
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
        alert('Please select/upload the CBSE Result PDF or Image before submitting for verification.');
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

function remove(r) {
    if (!confirm(`Delete Class ${r.class} results for ${r.academic_year}?`)) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${r.id}`);
}

function statusClass(status) {
    const map = {
        draft: 'bg-slate-100 text-slate-700 border-slate-200',
        submitted: 'bg-blue-50 text-blue-700 border-blue-200',
        verified: 'bg-amber-50 text-amber-700 border-amber-200',
        approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        published: 'bg-green-50 text-green-700 border-green-200',
        rejected: 'bg-red-50 text-red-700 border-red-200',
    };
    return map[status] || 'bg-slate-100 text-slate-600 border-slate-200';
}
</script>
