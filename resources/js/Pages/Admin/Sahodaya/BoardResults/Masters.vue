<template>
    <SahodayaAdminLayout title="Board result masters" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board result masters" eyebrow="Academic Results"
                    description="Manage exam streams, CBSE master subjects, and Academic Performance Index weights for this Sahodaya.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification`" class="btn-secondary text-sm">← Verification</Link>
            </template>
        </PageHeader>

        <!-- SECTION 1: CBSE BOARD SUBJECTS MASTER (ADD, EDIT, REMOVE, SEED) -->
        <section class="card space-y-5 border-2 border-indigo-100 bg-gradient-to-br from-indigo-50/20 to-white shadow-sm mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-indigo-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span>📚</span> CBSE Board Subjects Master
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Define and manage subjects available for Class X &amp; XII Subject-Wise Mark Entry across all member schools.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="seedStandardSubjects" class="btn-secondary text-xs px-3.5 py-2 font-bold text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100 flex items-center gap-1.5">
                        <span>⚡</span> Seed 23 Standard CBSE Subjects
                    </button>
                    <span class="text-xs font-bold text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg border border-gray-200">
                        {{ allBoardSubjects?.length ?? 0 }} Subjects
                    </span>
                </div>
            </div>

            <!-- Add Subject Form -->
            <form @submit.prevent="createSubject" class="bg-white rounded-xl border border-gray-200 p-4 shadow-xs grid sm:grid-cols-4 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label class="form-label text-xs font-semibold">Subject Label / Name *</label>
                    <input v-model="subjectForm.label" type="text" required class="field text-sm mt-1" placeholder="e.g. Physics, Accountancy, Fine Arts...">
                </div>
                <div>
                    <label class="form-label text-xs font-semibold">Code (Optional)</label>
                    <input v-model="subjectForm.code" type="text" class="field text-sm mt-1" placeholder="e.g. PHY042">
                </div>
                <div>
                    <button type="submit" class="btn-primary text-xs w-full py-2.5 font-bold shadow-xs">
                        + Add Subject
                    </button>
                </div>
            </form>

            <!-- Board Subjects List -->
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Configured Board Subjects</h3>
                <div v-if="allBoardSubjects?.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="subj in allBoardSubjects" :key="subj.id"
                         class="bg-white rounded-xl border border-gray-200 p-3 shadow-2xs flex items-center justify-between gap-2 hover:border-indigo-200 transition">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-gray-900 text-sm truncate">{{ subj.label }}</p>
                            <p v-if="subj.code" class="text-[11px] font-mono text-gray-400">Code: {{ subj.code }}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" @click="removeSubject(subj)" class="text-xs text-red-500 hover:text-red-700 font-semibold p-1" title="Remove Subject">
                                🗑
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="p-6 text-center text-gray-400 text-xs bg-white rounded-xl border border-dashed border-gray-200">
                    No custom subjects created yet. Click <strong>"Seed 23 Standard CBSE Subjects"</strong> above to auto-populate default subjects.
                </div>
            </div>
        </section>

        <!-- SECTION 2: API WEIGHTS & STREAM MASTER -->
        <div class="grid lg:grid-cols-2 gap-6">
            <section class="card space-y-4">
                <h2 class="font-semibold text-[#0f3d7a]">API weights</h2>
                <p class="text-xs text-slate-500">Weights must sum to 100.</p>
                <form class="grid grid-cols-2 gap-3" @submit.prevent="saveApi">
                    <label class="text-xs">
                        Pass %
                        <input v-model.number="apiForm.weight_pass_percent" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <label class="text-xs">
                        Distinctions
                        <input v-model.number="apiForm.weight_distinctions" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <label class="text-xs">
                        Highest mark
                        <input v-model.number="apiForm.weight_highest_mark" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <label class="text-xs">
                        Toppers
                        <input v-model.number="apiForm.weight_toppers" type="number" step="0.1" min="0" max="100" class="form-input mt-1" />
                    </label>
                    <div class="col-span-2 flex items-center justify-between">
                        <p class="text-xs" :class="apiSum === 100 ? 'text-emerald-700' : 'text-red-600'">Sum: {{ apiSum }}</p>
                        <button type="submit" class="btn-primary text-sm" :disabled="apiSum !== 100">Save weights</button>
                    </div>
                </form>
            </section>

            <section class="card space-y-4">
                <h2 class="font-semibold text-[#0f3d7a]">Add Sahodaya stream</h2>
                <form class="space-y-3" @submit.prevent="createStream">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs">Code
                            <input v-model="streamForm.code" class="form-input mt-1" required maxlength="40" />
                        </label>
                        <label class="text-xs">Label
                            <input v-model="streamForm.label" class="form-input mt-1" required maxlength="120" />
                        </label>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Subjects (optional — can be added later too)</p>
                        <SubjectPicker
                            :subjects="streamForm.default_subjects"
                            :subjects-by-category="subjectsByCategory"
                            @add="label => streamForm.default_subjects.push(label)"
                            @remove="label => streamForm.default_subjects = streamForm.default_subjects.filter(l => l !== label)"
                        />
                    </div>
                    <button type="submit" class="btn-primary text-sm">Create stream</button>
                </form>
            </section>
        </div>

        <section class="card mt-6 flex flex-wrap items-center justify-between gap-3 bg-amber-50/60 border-amber-100">
            <div>
                <h2 class="font-semibold text-[#0f3d7a] mb-1">Marks settings &amp; topper caps have moved</h2>
                <p class="text-xs text-slate-600">
                    "Out of" totals and Top-N/tie-break/rank-style settings are now per-academic-year, on the Board Results Settings page.
                </p>
            </div>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/settings`" class="btn-primary text-sm shrink-0">
                Open Board Results Settings →
            </Link>
        </section>

        <section class="card mt-6">
            <h2 class="font-semibold text-[#0f3d7a] mb-3">Exam streams</h2>
            <div class="space-y-3">
                <div v-for="s in streams" :key="s.id" class="border border-slate-200 rounded-lg p-3 space-y-3">
                    <div class="flex flex-wrap gap-3 items-end justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm">
                                {{ s.label }}
                                <span class="font-mono text-xs text-slate-500 ml-2">{{ s.code }}</span>
                                <span v-if="!s.sahodaya_id" class="ml-2 text-[10px] uppercase tracking-wide bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">Global</span>
                                <span v-else class="ml-2 text-[10px] uppercase tracking-wide bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded">Override</span>
                                <span v-if="!s.is_active" class="ml-2 text-[10px] uppercase text-red-600">Inactive</span>
                            </p>
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <input v-model="editForms[s.id].label" class="form-input text-sm" />
                                <input v-model.number="editForms[s.id].sort_order" type="number" class="form-input text-sm" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn-secondary text-xs" @click="saveStream(s)">Save</button>
                            <button v-if="s.sahodaya_id" type="button" class="text-xs text-red-600 hover:underline" @click="removeStream(s)">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SubjectPicker from '@/Components/ui/SubjectPicker.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    streams: { type: Array, default: () => [] },
    subjectsByCategory: { type: Object, default: () => ({}) },
    allBoardSubjects: { type: Array, default: () => [] },
    standardCbses: { type: Array, default: () => [] },
    apiConfig: { type: Object, default: () => ({}) },
    topperConfigs: { type: Array, default: () => [] },
    classXTotalMarks: { type: Number, default: 500 },
    streamTotalMarks: { type: Object, default: () => ({}) },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/board-results/masters`);

const apiForm = reactive({
    weight_pass_percent: Number(props.apiConfig.weight_pass_percent ?? 40),
    weight_distinctions: Number(props.apiConfig.weight_distinctions ?? 20),
    weight_highest_mark: Number(props.apiConfig.weight_highest_mark ?? 20),
    weight_toppers: Number(props.apiConfig.weight_toppers ?? 20),
    is_active: props.apiConfig.is_active ?? true,
});

const apiSum = computed(() =>
    Math.round((
        Number(apiForm.weight_pass_percent || 0)
        + Number(apiForm.weight_distinctions || 0)
        + Number(apiForm.weight_highest_mark || 0)
        + Number(apiForm.weight_toppers || 0)
    ) * 10) / 10
);

function saveApi() {
    router.put(`${base.value}/api-config`, { ...apiForm }, { preserveScroll: true });
}

// ── Board Subjects Master Form ───────────────────────────────────────────
const subjectForm = useForm({
    label: '',
    code: '',
    category: 'language',
});

function createSubject() {
    subjectForm.post(`${base.value}/subjects`, {
        preserveScroll: true,
        onSuccess: () => subjectForm.reset(),
    });
}

function removeSubject(subj) {
    if (!window.confirm(`Remove subject "${subj.label}"?`)) return;
    router.delete(`${base.value}/subjects/${subj.id}`, { preserveScroll: true });
}

function seedStandardSubjects() {
    router.post(`${base.value}/subjects/seed-standard`, {}, { preserveScroll: true });
}

// ── Stream Master ────────────────────────────────────────────────────────
const streamForm = reactive({ code: '', label: '', default_subjects: [] });

const editForms = reactive({});
for (const s of props.streams ?? []) {
    editForms[s.id] = reactive({
        label: s.label,
        sort_order: s.sort_order ?? 0,
        is_active: s.is_active,
        default_subjects: Array.isArray(s.default_subjects) ? [...s.default_subjects] : [],
    });
}

function createStream() {
    router.post(`${base.value}/streams`, { ...streamForm }, {
        preserveScroll: true,
        onSuccess: () => { streamForm.code = ''; streamForm.label = ''; streamForm.default_subjects = []; },
    });
}

function saveStream(s) {
    router.put(`${base.value}/streams/${s.id}`, {
        ...editForms[s.id],
    }, { preserveScroll: true });
}

function removeStream(s) {
    if (!window.confirm(`Remove or deactivate stream ${s.code}?`)) return;
    router.delete(`${base.value}/streams/${s.id}`, { preserveScroll: true });
}
</script>
