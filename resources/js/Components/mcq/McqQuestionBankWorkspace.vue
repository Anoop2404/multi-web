<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <Link :href="baseUrl" class="link-brand">← All question banks</Link>
        </div>

        <!-- Bank header -->
        <section class="card space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-slate-900">{{ bank.title }}</h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(bank.status)">{{ bank.status_label }}</span>
                        <span v-if="bank.locked" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Locked — exam started</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ bank.subject }}
                        <span v-if="bank.scope_label || bank.class_group_label"> · {{ bank.scope_label || bank.class_group_label }}</span>
                        · by {{ bank.owner_label }}
                    </p>
                    <p v-if="bank.description" class="mt-2 text-sm text-slate-600">{{ bank.description }}</p>
                    <p v-if="bank.exams.length" class="mt-2 text-xs text-slate-500">
                        Used in: {{ bank.exams.map((e) => e.title).join(', ') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="bank.status !== 'active'" type="button" class="btn-primary text-sm" @click="setStatus('active')">Mark Ready</button>
                    <button v-if="bank.status === 'active'" type="button" class="btn-secondary text-sm" @click="setStatus('draft')">Back to draft</button>
                    <button v-if="canContribute" type="button" class="btn-secondary text-sm" @click="contribute">Contribute copy to Sahodaya</button>
                    <button type="button" class="btn-secondary text-sm" @click="editingBank = !editingBank">Edit details</button>
                    <button v-if="bank.status !== 'archived'" type="button" class="btn-secondary text-sm" @click="setStatus('archived')">Archive</button>
                    <button v-if="!bank.exams.length" type="button" class="btn-secondary text-sm !text-red-600" @click="destroyBank">Delete</button>
                </div>
            </div>

            <form v-if="editingBank" class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" @submit.prevent="saveBank">
                <div class="sm:col-span-2">
                    <label class="form-label">Title</label>
                    <input v-model="bankForm.title" class="field">
                    <InputError :message="bankForm.errors.title" />
                </div>
                <div>
                    <label class="form-label">Subject</label>
                    <input v-model="bankForm.subject" class="field" list="mcq-bank-subjects">
                    <datalist id="mcq-bank-subjects"><option v-for="s in subjects" :key="s.value" :value="s.value" /></datalist>
                    <InputError :message="bankForm.errors.subject" />
                </div>
                <div>
                    <label class="form-label">Class category</label>
                    <select v-model="bankForm.class_category_id" class="field">
                        <option :value="null">Any / not scoped</option>
                        <option v-for="c in classCategories" :key="c.id" :value="c.id">{{ c.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Class</label>
                    <select v-model="bankForm.master_class_id" class="field">
                        <option :value="null">Any / not scoped</option>
                        <option v-for="c in masterClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div v-if="classGroups.length">
                    <label class="form-label">Class group</label>
                    <select v-model="bankForm.class_group" class="field">
                        <option :value="null">Any</option>
                        <option v-for="g in classGroups" :key="g.value" :value="g.value">{{ g.label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea v-model="bankForm.description" rows="2" class="field" />
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="bankForm.processing">Save details</button>
                    <button type="button" class="btn-secondary text-sm" @click="editingBank = false">Cancel</button>
                </div>
            </form>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-6">
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-center sm:col-span-1">
                    <p class="text-lg font-bold">{{ stats.total }}</p>
                    <p class="text-[11px] text-slate-500">Questions</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-center">
                    <p class="text-lg font-bold">{{ stats.max_score }}</p>
                    <p class="text-[11px] text-slate-500">Total marks</p>
                </div>
                <div v-for="(label, type) in typeLabels" :key="type" class="rounded-lg bg-slate-50 px-3 py-2 text-center">
                    <p class="text-lg font-bold">{{ stats.by_type?.[type] ?? 0 }}</p>
                    <p class="text-[11px] text-slate-500">{{ label }}</p>
                </div>
            </div>

            <div v-if="stats.by_difficulty && stats.total > 0" class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                <span class="font-bold text-slate-600">Difficulty Blueprint:</span>
                <span class="rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-0.5 font-semibold">Easy: {{ stats.by_difficulty.easy ?? 0 }}</span>
                <span class="rounded-full bg-amber-100 text-amber-800 px-2.5 py-0.5 font-semibold">Medium: {{ stats.by_difficulty.medium ?? 0 }}</span>
                <span class="rounded-full bg-red-100 text-red-800 px-2.5 py-0.5 font-semibold">Hard: {{ stats.by_difficulty.hard ?? 0 }}</span>
            </div>

            <p v-if="stats.total > stats.gradable" class="text-xs font-medium text-amber-700">
                {{ stats.total - stats.gradable }} question(s) have no answer key and won't be marked.
            </p>
        </section>

        <McqQuestionImport v-if="!bank.locked" :base-url="baseUrl" :bank-id="bank.id" :type-labels="typeLabels" :locked="bank.locked" />

        <!-- Questions -->
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h3 class="section-title !mb-0">Questions</h3>
                    <label v-if="!bank.locked && questions.length" class="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" v-model="allSelected" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Select all ({{ questions.length }})</span>
                    </label>
                </div>
                <button v-if="!bank.locked" type="button" class="btn-primary text-sm" @click="openEditor(null)">+ Add question</button>
            </div>

            <!-- Bulk action toolbar -->
            <div v-if="!bank.locked && selectedIds.length" class="sticky top-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-indigo-200 bg-indigo-50/95 p-3.5 shadow-md backdrop-blur">
                <div class="flex items-center gap-2 text-xs font-bold text-indigo-900">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-[10px] text-white">{{ selectedIds.length }}</span>
                    <span>question(s) selected</span>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button" class="btn-secondary text-xs !py-1.5" @click="openBulkMarks">Set marks…</button>
                    <button type="button" class="btn-secondary text-xs !py-1.5 !text-red-700 !border-red-200 hover:!bg-red-50" @click="bulkDelete">Delete selected</button>
                    <button type="button" class="text-slate-500 hover:text-slate-700 underline text-xs ml-1" @click="selectedIds = []">Clear</button>
                </div>
            </div>

            <EmptyState v-if="!questions.length" title="No questions yet"
                        description="Add questions one by one, or import them from the Excel template." icon="📝" class="card py-10" />

            <article v-for="(q, index) in questions" :key="q.id" class="card space-y-3" :class="selectedIds.includes(q.id) ? 'ring-2 ring-indigo-500/60 bg-indigo-50/15' : ''">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <label v-if="!bank.locked" class="cursor-pointer">
                            <input type="checkbox" :value="q.id" v-model="selectedIds" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        </label>
                        <span class="rounded-md bg-slate-900 px-2 py-0.5 font-bold text-white">Q{{ q.number }}</span>
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">{{ q.type_label }}</span>
                        <span class="text-slate-500">{{ q.marks }} mark{{ q.marks == 1 ? '' : 's' }}<span v-if="q.negative_mark > 0"> · −{{ q.negative_mark }} if wrong</span></span>
                        <span v-if="q.difficulty" class="text-slate-500 capitalize">· {{ q.difficulty }}</span>
                        <span v-if="!q.gradable" class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">No answer key</span>
                    </div>
                    <div v-if="!bank.locked" class="flex items-center gap-1 text-xs">
                        <button type="button" class="btn-ghost !px-2 !py-1" :disabled="index === 0" title="Move up" @click="move(q, 'up')">↑</button>
                        <button type="button" class="btn-ghost !px-2 !py-1" :disabled="index === questions.length - 1" title="Move down" @click="move(q, 'down')">↓</button>
                        <button type="button" class="btn-ghost !px-2 !py-1 font-semibold text-slate-700" title="Duplicate question" @click="cloneQuestion(q)">Clone</button>
                        <button type="button" class="btn-ghost !px-2 !py-1 font-semibold" @click="openEditor(q)">Edit</button>
                        <button type="button" class="btn-ghost !px-2 !py-1 font-semibold !text-red-600" @click="destroyQuestion(q)">Delete</button>
                    </div>
                </div>
                <McqQuestionView :question="q" show-key />
            </article>
        </section>

        <!-- Bulk Marks Modal -->
        <Modal :show="bulkMarksModalOpen" title="Set marks for selected questions" size="md" @close="bulkMarksModalOpen = false">
            <form class="space-y-4" @submit.prevent="submitBulkMarks">
                <p class="text-xs text-slate-600">
                    Applying marks to <strong>{{ selectedIds.length }}</strong> selected question(s).
                </p>
                <div>
                    <label class="form-label">Marks per correct answer</label>
                    <input v-model.number="bulkMarksForm.marks" type="number" step="0.01" min="0.01" class="field" required>
                </div>
                <div>
                    <label class="form-label">Minus marks for wrong answer (optional)</label>
                    <input v-model.number="bulkMarksForm.negative_mark" type="number" step="0.01" min="0" class="field" placeholder="0">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary text-sm" @click="bulkMarksModalOpen = false">Cancel</button>
                    <button type="submit" class="btn-primary text-sm" :disabled="bulkMarksForm.processing">Apply marks</button>
                </div>
            </form>
        </Modal>

        <Modal :show="editorOpen" :title="editing ? `Edit question ${editing.number}` : 'Add question'" size="xl" :close-on-backdrop="false" @close="editorOpen = false">
            <McqQuestionEditor
                v-if="editorOpen"
                :key="editorKey"
                :base-url="baseUrl"
                :bank-id="bank.id"
                :question="editing"
                :type-labels="typeLabels"
                :difficulties="difficulties"
                @close="editorOpen = false"
            />
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/ui/Modal.vue';
import McqQuestionView from '@/Components/mcq/McqQuestionView.vue';
import McqQuestionEditor from '@/Components/mcq/McqQuestionEditor.vue';
import McqQuestionImport from '@/Components/mcq/McqQuestionImport.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    bank: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    typeLabels: { type: Object, required: true },
    difficulties: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    canContribute: { type: Boolean, default: false },
    baseUrl: { type: String, required: true },
});

const { confirm } = useConfirm();
const bankUrl = `${props.baseUrl}/${props.bank.id}`;

const selectedIds = ref([]);
const allSelected = computed({
    get: () => props.questions.length > 0 && selectedIds.value.length === props.questions.length,
    set: (val) => { selectedIds.value = val ? props.questions.map(q => q.id) : []; },
});

const bulkMarksModalOpen = ref(false);
const bulkMarksForm = useForm({
    marks: 1,
    negative_mark: 0,
});

const editingBank = ref(false);
const bankForm = useForm({
    title: props.bank.title,
    subject: props.bank.subject,
    class_group: props.bank.class_group,
    class_category_id: props.bank.class_category_id ?? null,
    master_class_id: props.bank.master_class_id ?? null,
    description: props.bank.description ?? '',
    status: props.bank.status,
});

const editorOpen = ref(false);
const editing = ref(null);
const editorKey = ref(0);

function statusClass(status) {
    return {
        active: 'bg-emerald-100 text-emerald-800',
        draft: 'bg-slate-100 text-slate-700',
        archived: 'bg-slate-200 text-slate-500',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

function saveBank() {
    bankForm.status = props.bank.status;
    bankForm.put(bankUrl, { preserveScroll: true, onSuccess: () => { editingBank.value = false; } });
}

function setStatus(status) {
    router.put(bankUrl, {
        title: props.bank.title,
        subject: props.bank.subject,
        class_group: props.bank.class_group,
        class_category_id: props.bank.class_category_id,
        master_class_id: props.bank.master_class_id,
        description: props.bank.description,
        status,
    }, { preserveScroll: true });
}

function contribute() {
    router.post(`${bankUrl}/contribute`, {}, { preserveScroll: true });
}

async function destroyBank() {
    if (!(await confirm({ title: 'Delete this question bank?', message: 'All its questions and images are deleted. This cannot be undone.', confirmLabel: 'Delete bank', destructive: true }))) return;
    router.delete(bankUrl);
}

function openEditor(question) {
    editing.value = question;
    editorKey.value++;
    editorOpen.value = true;
}

function move(question, direction) {
    router.post(`${bankUrl}/questions/${question.id}/move`, { direction }, { preserveScroll: true });
}

async function destroyQuestion(question) {
    if (!(await confirm({ title: `Delete question ${question.number}?`, message: 'This cannot be undone.', confirmLabel: 'Delete', destructive: true }))) return;
    router.delete(`${bankUrl}/questions/${question.id}`, { preserveScroll: true });
}

function cloneQuestion(question) {
    router.post(`${bankUrl}/questions/${question.id}/clone`, {}, {
        preserveScroll: true,
    });
}

async function bulkDelete() {
    if (!selectedIds.value.length) return;
    if (!(await confirm({
        title: `Delete ${selectedIds.value.length} question(s)?`,
        message: 'All selected questions and their diagrams will be permanently removed. This cannot be undone.',
        confirmLabel: 'Delete selected',
        destructive: true,
    }))) return;

    router.post(`${bankUrl}/questions/bulk`, {
        action: 'delete',
        ids: selectedIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; },
    });
}

function openBulkMarks() {
    if (!selectedIds.value.length) return;
    bulkMarksModalOpen.value = true;
}

function submitBulkMarks() {
    bulkMarksForm.post(`${bankUrl}/questions/bulk`, {
        preserveScroll: true,
        data: {
            action: 'set_marks',
            ids: selectedIds.value,
            marks: bulkMarksForm.marks,
            negative_mark: bulkMarksForm.negative_mark,
        },
        onSuccess: () => {
            bulkMarksModalOpen.value = false;
            selectedIds.value = [];
        },
    });
}
</script>
