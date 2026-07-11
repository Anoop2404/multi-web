<template>
    <SchoolAdminLayout title="Board Results" :school="school" :show-header-title="false">
        <PageHeader title="Board Results" eyebrow="Academic Results"
            description="Enter CBSE AISSE/AISSCE summaries, upload the official PDF, add toppers, then submit for Sahodaya verification." />

        <div class="space-y-6">
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">Add / Update Board Result</h3>
                <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Class *</label>
                            <select v-model="form.class" required class="field" @change="onClassChange">
                                <option value="10">Class X (AISSE)</option>
                                <option value="12">Class XII (AISSCE)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Examination type *</label>
                            <select v-model="form.examination_type" required class="field">
                                <option v-for="t in examinationTypes" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Academic Year *</label>
                            <input v-model="form.academic_year" type="text" required placeholder="2024-25" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Pass % *</label>
                            <input v-model="form.pass_percent" type="number" required min="0" max="100" step="0.01" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Total Appeared *</label>
                            <input v-model="form.total_appeared" type="number" required min="0" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Passed *</label>
                            <input v-model="form.pass_count" type="number" required min="0" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Distinctions</label>
                            <input v-model="form.distinctions" type="number" min="0" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">First class</label>
                            <input v-model="form.first_class" type="number" min="0" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Highest mark</label>
                            <input v-model="form.highest_mark" type="number" min="0" max="100" step="0.01" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Average mark</label>
                            <input v-model="form.average_mark" type="number" min="0" max="100" step="0.01" class="field">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="form-label mb-1.5">Remarks</label>
                            <textarea v-model="form.remarks" rows="2" class="field" placeholder="Optional notes for Sahodaya reviewers"></textarea>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">CBSE result PDF</label>
                            <input type="file" accept="application/pdf" class="field text-sm" @change="form.result_pdf = $event.target.files[0]">
                            <p class="text-xs text-gray-400 mt-1">Required before Submit for verification.</p>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Attachments (Word/Excel)</label>
                            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" class="field text-sm"
                                   @change="form.attachments = Array.from($event.target.files || [])">
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                            Save & Add Toppers →
                        </button>
                        <p class="text-xs text-gray-400">Unique per school + class + examination type + year.</p>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
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

const props = defineProps({
    school: Object,
    results: { type: Array, default: () => [] },
    examinationTypes: { type: Array, default: () => ['AISSE', 'AISSCE'] },
    statuses: { type: Array, default: () => [] },
    auditHistory: { type: Array, default: () => [] },
    topperCap: { type: Number, default: 5 },
});

function formatAuditTime(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString(); } catch { return iso; }
}

const form = useForm({
    class: '10',
    examination_type: 'AISSE',
    academic_year: '',
    total_appeared: '',
    pass_count: '',
    pass_percent: '',
    distinctions: '',
    first_class: '',
    highest_mark: '',
    average_mark: '',
    remarks: '',
    result_pdf: null,
    attachments: [],
});

function onClassChange() {
    form.examination_type = String(form.class) === '12' ? 'AISSCE' : 'AISSE';
}

function submit() {
    form.post(`/school-admin/${props.school.id}/board-results`, { forceFormData: true });
}

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
