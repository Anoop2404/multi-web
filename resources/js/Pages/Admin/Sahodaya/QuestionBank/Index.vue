<template>
    <SahodayaAdminLayout title="Question Bank" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <div class="max-w-full overflow-x-hidden">
        <PageHeader
            title="Question Bank"
            eyebrow="Membership"
            description="Upload class-wise question papers for public download from your website."
        >
            <template #actions>
                <button type="button" class="btn-secondary text-sm" @click="showForm ? closeForm() : openAddForm()">
                    {{ showForm ? 'Hide upload form' : 'Upload document' }}
                </button>
            </template>
        </PageHeader>

        <form v-show="showForm" @submit.prevent="submitForm" class="card mb-6 space-y-4">
            <h3 class="section-title">{{ isEditing ? 'Edit document' : 'Upload new document' }}</h3>
            <FormGrid>
                <FormField label="Title" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" type="text" required class="field" placeholder="e.g. Half Yearly Exam — Mathematics">
                    </template>
                </FormField>
                <FormField label="Class" required>
                    <template #default="{ id }">
                        <SearchableSelect :id="id" v-model="form.master_class_id" :options="classOptions"
                                          :all-option="false" placeholder="Select class" />
                    </template>
                </FormField>
                <FormField label="Subject">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.subject" type="text" placeholder="e.g. Mathematics" class="field">
                    </template>
                </FormField>
                <FormField label="Academic year">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.academic_year" type="text" placeholder="2025-26" class="field">
                    </template>
                </FormField>
                <FormField :label="isEditing ? 'PDF / document (leave blank to keep current file)' : 'PDF / document'" class-extra="sm:col-span-2" :required="!isEditing">
                    <template #default="{ id }">
                        <label :for="id"
                               class="flex flex-col items-center justify-center w-full min-h-[7rem] border-2 border-dashed rounded-xl cursor-pointer transition"
                               :class="dragover ? 'border-violet-400 bg-violet-50' : 'border-slate-200 hover:border-violet-300 hover:bg-slate-50'"
                               @dragover.prevent="dragover = true"
                               @dragleave="dragover = false"
                               @drop.prevent="onDrop">
                            <div v-if="form.file" class="flex items-center gap-2 text-sm text-emerald-700 font-semibold">
                                <span>{{ form.file.name }}</span>
                                <button type="button" @click.prevent="form.file = null" class="text-red-500 hover:text-red-700">Remove</button>
                            </div>
                            <div v-else class="text-center px-4">
                                <p class="text-sm text-slate-500">Drop PDF here or <span class="text-violet-600 font-semibold">browse</span></p>
                            </div>
                            <input :id="id" type="file" accept=".pdf,.doc,.docx" class="sr-only" :required="!isEditing"
                                   @change="form.file = $event.target.files[0]">
                        </label>
                    </template>
                </FormField>
            </FormGrid>
            <FormActions>
                <button v-if="isEditing" type="button" class="btn-secondary" @click="closeForm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing || (!isEditing && !form.file) || !form.master_class_id">
                    {{ form.processing ? (isEditing ? 'Saving…' : 'Uploading…') : (isEditing ? 'Save changes' : 'Upload document') }}
                </button>
            </FormActions>
        </form>

        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" @click="activeClass = ''"
                    :class="activeClass === '' ? 'btn-primary !py-1.5 !px-3 text-xs' : 'btn-secondary !py-1.5 !px-3 text-xs'">
                All ({{ documents.length }})
            </button>
            <button v-for="c in masterClasses" :key="c.id" type="button"
                    @click="activeClass = c.id"
                    :class="activeClass === c.id ? 'btn-primary !py-1.5 !px-3 text-xs' : 'btn-secondary !py-1.5 !px-3 text-xs'">
                {{ c.name }} ({{ documents.filter(d => d.master_class_id === c.id).length }})
            </button>
        </div>

        <div class="flex flex-wrap gap-3 items-center mb-4">
            <input v-model="searchQuery" type="search" class="field flex-1 min-w-[12rem] max-w-md"
                   placeholder="Search title or subject…" autocomplete="off">
            <button v-if="searchQuery.trim()" type="button" class="btn-secondary text-sm" @click="searchQuery = ''">Clear</button>
        </div>

        <p class="text-xs text-slate-500 mb-3">
            {{ filtered.length }} document{{ filtered.length === 1 ? '' : 's' }} shown
        </p>

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!filtered.length" title="No documents"
                        :description="searchQuery.trim() ? 'Try another search term.' : 'Upload a document using the form above.'"
                        icon="📚" class="p-10" />
            <div v-else class="hidden md:block overflow-x-auto">
                <table class="data-table w-full min-w-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th class="hidden sm:table-cell">Class</th>
                            <th class="hidden sm:table-cell">Subject</th>
                            <th class="hidden lg:table-cell">Academic Year</th>
                            <th class="hidden lg:table-cell">Downloads</th>
                            <th class="w-28 text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in filtered" :key="d.id">
                            <td>
                                <p class="font-medium text-slate-900 line-clamp-2">{{ d.title }}</p>
                            </td>
                            <td class="hidden sm:table-cell">
                                <span class="text-[11px] font-semibold px-2 py-1 rounded-full bg-violet-100 text-violet-700">
                                    {{ d.master_class?.name ?? '—' }}
                                </span>
                            </td>
                            <td class="hidden sm:table-cell text-xs text-slate-500">{{ d.subject || '—' }}</td>
                            <td class="hidden lg:table-cell text-xs text-slate-500">{{ d.academic_year || '—' }}</td>
                            <td class="hidden lg:table-cell text-xs text-slate-500">{{ d.download_count ?? 0 }}</td>
                            <td class="text-right whitespace-nowrap space-x-2">
                                <button type="button" @click="openEditForm(d)" class="text-xs text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button type="button" @click="remove(d)" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="filtered.length" class="md:hidden divide-y divide-slate-100">
                <article v-for="d in filtered" :key="`m-${d.id}`" class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-slate-900">{{ d.title }}</p>
                        <span class="text-[11px] font-semibold px-2 py-1 rounded-full shrink-0 bg-violet-100 text-violet-700">
                            {{ d.master_class?.name ?? '—' }}
                        </span>
                    </div>
                    <p v-if="d.subject" class="text-xs text-slate-500">{{ d.subject }}</p>
                    <p class="text-xs text-slate-500">
                        {{ d.academic_year || 'No year set' }} · {{ d.download_count ?? 0 }} downloads
                    </p>
                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="openEditForm(d)" class="text-xs text-indigo-600 hover:text-indigo-800">Edit</button>
                        <button type="button" @click="remove(d)" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                    </div>
                </article>
            </div>
        </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    documents: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
});

const classOptions = computed(() => props.masterClasses.map((c) => ({ value: c.id, label: c.name })));

const showForm = ref(false);
const dragover = ref(false);
const activeClass = ref('');
const searchQuery = ref('');
const isEditing = ref(false);
const editingDocument = ref(null);

const filtered = computed(() => {
    let rows = props.documents;
    if (activeClass.value) {
        rows = rows.filter((d) => d.master_class_id === activeClass.value);
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return rows;
    }
    return rows.filter((d) =>
        [d.title, d.subject, d.academic_year].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

const form = useForm({
    title: '', master_class_id: '', subject: '', academic_year: '', file: null,
});

function onDrop(e) {
    dragover.value = false;
    const f = e.dataTransfer.files[0];
    if (f) form.file = f;
}

function openAddForm() {
    isEditing.value = false;
    editingDocument.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEditForm(d) {
    isEditing.value = true;
    editingDocument.value = d;
    form.clearErrors();
    form.title = d.title;
    form.master_class_id = d.master_class_id;
    form.subject = d.subject ?? '';
    form.academic_year = d.academic_year ?? '';
    form.file = null;
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    isEditing.value = false;
    editingDocument.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (isEditing.value && editingDocument.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/sahodaya-admin/${props.sahodaya.id}/question-bank/${editingDocument.value.id}`,
            { forceFormData: true, onSuccess: () => closeForm() },
        );
    } else {
        form.post(`/sahodaya-admin/${props.sahodaya.id}/question-bank`, {
            forceFormData: true,
            onSuccess: () => closeForm(),
        });
    }
}

async function remove(d) {
    if (!(await confirm({ message: `Delete "${d.title}"?` }))) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/question-bank/${d.id}`);
}
</script>
