<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#041525]/60 backdrop-blur-sm" @click="close"></div>
        <div class="relative modal-shell max-w-xl max-h-[90vh] flex flex-col w-full">
            <div class="modal-head shrink-0">
                <div>
                    <h3 class="font-bold text-[#041525]">Bulk upload students</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Import many records at once — verify each student after upload.</p>
                </div>
                <button type="button" @click="close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            <div class="px-6 pt-4 shrink-0 flex flex-wrap gap-2 border-b border-slate-100">
                <button v-for="tab in tabs" :key="tab.id" type="button"
                        class="text-xs font-semibold px-3 py-1.5 rounded-full transition"
                        :class="activeTab === tab.id ? 'bg-[#0f3d7a] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="activeTab = tab.id">
                    {{ tab.label }}
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4">
                <!-- CSV -->
                <div v-show="activeTab === 'csv'" class="space-y-4">
                    <div class="rounded-xl border border-[#dbeafe] bg-[#f0f9ff] p-4 text-sm text-[#041525] space-y-2">
                        <p class="font-semibold">Spreadsheet import (recommended)</p>
                        <p class="text-xs text-gray-600">Download the template, fill in Excel or Google Sheets, save as CSV, then upload. No row limit.</p>
                        <ul class="list-disc list-inside text-xs text-gray-600 space-y-0.5">
                            <li><strong>full_name</strong> — required</li>
                            <li><strong>class_name</strong> — required, must match your class master</li>
                            <li><strong>gender</strong> — optional (male / female / other)</li>
                            <li><strong>dob</strong> — optional (YYYY-MM-DD)</li>
                            <li><strong>email</strong> — optional parent email</li>
                        </ul>
                    </div>

                    <p v-if="classNames.length" class="text-xs text-gray-500">
                        <span class="font-semibold text-gray-700">Valid class names:</span>
                        {{ classNames.join(', ') }}
                    </p>

                    <a :href="templateUrl"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-[#0f3d7a] hover:underline">
                        ↓ Download CSV template
                    </a>

                    <form @submit.prevent="submitCsv" class="space-y-3">
                        <div>
                            <label class="form-label mb-1.5">CSV file *</label>
                            <input type="file" accept=".csv,.txt,text/csv" required @change="onCsvFile"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                            <p v-if="csvForm.errors.file" class="text-xs text-red-500 mt-1">{{ csvForm.errors.file }}</p>
                        </div>

                        <button v-if="csvForm.file && !previewData" type="button" class="btn-secondary text-sm"
                                :disabled="previewLoading" @click="runPreview">
                            {{ previewLoading ? 'Checking…' : 'Preview before import' }}
                        </button>

                        <div v-if="previewData" class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                            <p class="text-xs font-semibold text-slate-700">
                                Preview: {{ previewData.total_rows }} row(s) ·
                                {{ previewData.valid?.length ?? 0 }} valid shown ·
                                {{ previewData.errors?.length ?? 0 }} error(s)
                            </p>
                            <div v-if="previewData.errors?.length" class="max-h-24 overflow-y-auto text-xs text-red-600 space-y-0.5">
                                <p v-for="(err, i) in previewData.errors.slice(0, 20)" :key="i">Row {{ err.row }}: {{ err.message }}</p>
                            </div>
                            <table v-if="previewData.valid?.length" class="w-full text-xs">
                                <thead><tr><th class="text-left">Name</th><th class="text-left">Class</th><th>Gender</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in previewData.valid.slice(0, 10)" :key="row.row">
                                        <td>{{ row.name }}</td><td>{{ row.class }}</td><td>{{ row.gender || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="importResult?.errors?.length" class="rounded-lg border border-red-100 bg-red-50 p-3 max-h-32 overflow-y-auto">
                            <p class="text-xs font-semibold text-red-700 mb-1">Import issues</p>
                            <ul class="text-xs text-red-600 space-y-0.5">
                                <li v-for="(err, i) in importResult.errors" :key="i">Row {{ err.row }}: {{ err.message }}</li>
                            </ul>
                        </div>

                        <FormActions>
                            <button type="button" @click="close" class="btn-ghost">Cancel</button>
                            <button type="submit" :disabled="csvForm.processing || !classNames.length" class="btn-primary">
                                {{ csvForm.processing ? 'Importing…' : 'Import CSV' }}
                            </button>
                        </FormActions>
                    </form>
                </div>

                <!-- Grid with photos -->
                <div v-show="activeTab === 'grid'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-sm text-gray-700 space-y-2">
                        <p class="font-semibold text-slate-900">Add up to 25 students with photos</p>
                        <p class="text-xs">Use the grid when you have photos ready for each student. Each row needs name, class, gender, date of birth, and a photo.</p>
                    </div>
                    <Link :href="bulkGridUrl" class="btn-primary inline-flex" @click="close">
                        Open bulk add grid →
                    </Link>
                </div>

                <!-- Photo ZIP -->
                <div v-show="activeTab === 'zip'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-sm text-gray-700 space-y-2">
                        <p class="font-semibold text-slate-900">Attach photos to existing students</p>
                        <p class="text-xs">Upload a ZIP of image files named by each student&apos;s <strong>registration number</strong> (e.g. <code class="font-mono">TST001.jpg</code>). JPG, PNG, or WebP.</p>
                    </div>
                    <form @submit.prevent="submitZip" class="space-y-3">
                        <div>
                            <label class="form-label mb-1.5">ZIP file *</label>
                            <input type="file" accept=".zip,application/zip" required @change="onZipFile"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                            <p v-if="zipForm.errors.zip" class="text-xs text-red-500 mt-1">{{ zipForm.errors.zip }}</p>
                        </div>
                        <FormActions>
                            <button type="button" @click="close" class="btn-ghost">Cancel</button>
                            <button type="submit" :disabled="zipForm.processing" class="btn-primary">
                                {{ zipForm.processing ? 'Uploading…' : 'Upload photos' }}
                            </button>
                        </FormActions>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    schoolId: { type: String, required: true },
    classNames: { type: Array, default: () => [] },
    initialTab: { type: String, default: 'csv' },
});

const emit = defineEmits(['update:modelValue']);

const page = usePage();
const activeTab = ref(props.initialTab);

const tabs = [
    { id: 'csv', label: 'CSV / Excel' },
    { id: 'grid', label: 'Grid + photos' },
    { id: 'zip', label: 'Photo ZIP' },
];

const templateUrl = computed(() => `/school-admin/${props.schoolId}/students/import/template`);
const bulkGridUrl = computed(() => `/school-admin/${props.schoolId}/students/bulk`);
const importResult = computed(() => page.props.flash?.importResult ?? null);

const csvForm = useForm({ file: null });
const zipForm = useForm({ zip: null });
const previewData = ref(null);
const previewLoading = ref(false);

watch(() => props.modelValue, (open) => {
    if (open) activeTab.value = props.initialTab;
    if (!open) previewData.value = null;
});

function close() {
    emit('update:modelValue', false);
}

function onCsvFile(e) {
    csvForm.file = e.target.files[0] ?? null;
    previewData.value = null;
}

async function runPreview() {
    if (!csvForm.file) return;
    previewLoading.value = true;
    previewData.value = null;
    const formData = new FormData();
    formData.append('file', csvForm.file);
    try {
        const res = await fetch(`/school-admin/${props.schoolId}/students/import/preview`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body: formData,
        });
        previewData.value = await res.json();
    } finally {
        previewLoading.value = false;
    }
}

function onZipFile(e) {
    zipForm.zip = e.target.files[0] ?? null;
}

function submitCsv() {
    csvForm.post(`/school-admin/${props.schoolId}/students/import`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            csvForm.reset();
            const result = usePage().props.flash?.importResult;
            if (!result?.errors?.length) {
                close();
            }
        },
    });
}

function submitZip() {
    zipForm.post(`/school-admin/${props.schoolId}/students/photos-zip`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            zipForm.reset();
            close();
        },
    });
}
</script>
