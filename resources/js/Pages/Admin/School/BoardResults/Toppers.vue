<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Board Results"
                    :description="isClass12 ? 'Class XII — enter overall toppers and subject-wise marks (out of 100).' : 'Class X — enter overall toppers with total marks.'" />

        <div class="max-w-4xl space-y-6">
            <!-- Subject-wise leaders (Class XII) -->
            <div v-if="isClass12 && subjectWiseLeaders.length" class="card">
                <h3 class="section-title">Subject-wise toppers</h3>
                <p class="section-desc mb-4">Highest mark per subject among students you have added below.</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="row in subjectWiseLeaders" :key="row.subject"
                         class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ row.subject }}</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ row.name }}</p>
                        <p class="text-sm text-gray-500">{{ row.marks }} / 100
                            <span v-if="row.stream" class="text-xs">· {{ row.stream }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Add / edit topper -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">{{ editingId ? 'Edit topper' : 'Add topper' }}</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Student name *</label>
                            <input v-model="form.name" type="text" required class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Overall percentage *</label>
                            <input v-model="form.percentage" type="number" required min="0" max="100" step="0.01" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Overall rank</label>
                            <input v-model="form.rank" type="number" min="1" placeholder="1" class="field">
                        </div>
                        <div v-if="isClass12">
                            <label class="form-label mb-1.5">Stream *</label>
                            <select v-model="form.stream_key" required class="field" @change="onStreamChange">
                                <option value="">Select stream</option>
                                <option v-for="(label, key) in streamOptions" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Total marks (overall)</label>
                            <input v-model="form.total_marks" type="number" min="0" class="field" placeholder="e.g. 500">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Marks obtained (overall)</label>
                            <input v-model="form.marks_obtained" type="number" min="0" class="field" placeholder="e.g. 485">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Photo</label>
                            <input type="file" accept="image/*" class="field text-sm" @change="form.photo = $event.target.files[0]">
                        </div>
                        <div class="flex items-center gap-2 pt-5">
                            <input id="is_perfect" v-model="form.is_perfect_scorer" type="checkbox" class="rounded">
                            <label for="is_perfect" class="text-sm text-gray-700">Perfect scorer</label>
                        </div>
                    </div>

                    <div v-if="isClass12 && form.stream_key" class="border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-1">Subject-wise marks (out of 100)</h4>
                        <p class="text-xs text-gray-500 mb-3">Enter marks for each subject. Subject-wise toppers are calculated automatically.</p>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div v-for="subject in activeSubjects" :key="subject">
                                <label class="form-label mb-1 text-xs">{{ subject }}</label>
                                <input
                                    v-model="form.subject_marks[subject]"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="field text-sm"
                                    placeholder="—"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ editingId ? 'Save changes' : 'Add topper' }}
                        </button>
                        <button v-if="editingId" type="button" class="btn-secondary text-sm" @click="cancelEdit">Cancel edit</button>
                        <Link :href="`/school-admin/${school.id}/board-results`" class="text-sm text-gray-500 hover:text-gray-700">← Back to results</Link>
                    </div>
                </form>
            </div>

            <!-- Toppers list -->
            <div class="card card--flush">
                <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="font-bold text-gray-800">Overall toppers ({{ boardResult.toppers?.length ?? 0 }})</h3>
                </div>
                <div v-if="sortedToppers.length" class="divide-y divide-gray-50">
                    <div v-for="t in sortedToppers" :key="t.id" class="px-5 py-4">
                        <div class="flex items-start gap-4">
                            <img v-if="t.photo" :src="t.photo" class="w-12 h-12 rounded-full object-cover border border-gray-100 shrink-0" alt="">
                            <div v-else class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold shrink-0">
                                {{ t.name[0] }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            #{{ t.rank ?? '—' }} · {{ t.name }}
                                            <span v-if="t.is_perfect_scorer" class="ml-1 text-xs bg-yellow-50 text-yellow-700 px-1.5 py-0.5 rounded">Perfect</span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            <span v-if="t.stream">{{ t.stream }}</span>
                                            <span v-if="t.marks_obtained && t.total_marks"> · {{ t.marks_obtained }}/{{ t.total_marks }}</span>
                                        </p>
                                    </div>
                                    <p class="text-lg font-bold text-indigo-600 shrink-0">{{ t.percentage }}%</p>
                                </div>

                                <div v-if="isClass12 && t.subject_marks && Object.keys(t.subject_marks).length" class="mt-3 flex flex-wrap gap-2">
                                    <span v-for="(mark, subject) in t.subject_marks" :key="subject"
                                          class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-700">
                                        {{ subject }}: <strong>{{ mark }}</strong>
                                    </span>
                                </div>

                                <div class="mt-2 flex gap-3 text-xs">
                                    <button type="button" class="text-indigo-600 font-semibold hover:underline" @click="startEdit(t)">Edit</button>
                                    <button type="button" class="text-red-400 hover:underline" @click="remove(t)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-gray-400 text-sm">
                    No toppers added yet.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed, ref, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:             Object,
    boardResult:        Object,
    isClass12:          { type: Boolean, default: false },
    streamOptions:      { type: Object, default: () => ({}) },
    subjectsByStream:   { type: Object, default: () => ({}) },
    subjectWiseLeaders: { type: Array, default: () => [] },
});

const pageTitle = computed(() => `Toppers — Class ${props.boardResult.class} (${props.boardResult.academic_year})`);

const sortedToppers = computed(() =>
    [...(props.boardResult.toppers ?? [])].sort((a, b) => (a.rank ?? 999) - (b.rank ?? 999)),
);

const editingId = ref(null);

const form = useForm({
    name: '',
    percentage: '',
    rank: '',
    stream_key: '',
    total_marks: '',
    marks_obtained: '',
    is_perfect_scorer: false,
    photo: null,
    subject_marks: {},
});

const activeSubjects = computed(() => props.subjectsByStream[form.stream_key] ?? []);

function blankSubjectMarks(streamKey) {
    const marks = {};
    for (const subject of props.subjectsByStream[streamKey] ?? []) {
        marks[subject] = '';
    }
    return marks;
}

function onStreamChange() {
    const existing = { ...form.subject_marks };
    form.subject_marks = blankSubjectMarks(form.stream_key);
    for (const subject of activeSubjects.value) {
        if (existing[subject] !== undefined && existing[subject] !== '') {
            form.subject_marks[subject] = existing[subject];
        }
    }
}

function streamKeyFromTopper(t) {
    if (!t.stream) return '';
    const entry = Object.entries(props.streamOptions).find(([, label]) => label === t.stream);
    return entry?.[0] ?? 'other';
}

function startEdit(t) {
    editingId.value = t.id;
    form.name = t.name;
    form.percentage = t.percentage;
    form.rank = t.rank ?? '';
    form.stream_key = streamKeyFromTopper(t);
    form.total_marks = t.total_marks ?? '';
    form.marks_obtained = t.marks_obtained ?? '';
    form.is_perfect_scorer = !!t.is_perfect_scorer;
    form.photo = null;
    form.subject_marks = blankSubjectMarks(form.stream_key);
    for (const [subject, mark] of Object.entries(t.subject_marks ?? {})) {
        form.subject_marks[subject] = mark;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
    form.subject_marks = {};
}

function submit() {
    const base = `/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers`;
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            cancelEdit();
        },
    };

    if (editingId.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(`${base}/${editingId.value}`, options);
    } else {
        form.post(base, options);
    }
}

function remove(t) {
    if (!confirm(`Remove topper "${t.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${t.id}`);
}

watch(() => form.stream_key, (key) => {
    if (!key || editingId.value) return;
    if (Object.keys(form.subject_marks).length === 0) {
        form.subject_marks = blankSubjectMarks(key);
    }
});
</script>
