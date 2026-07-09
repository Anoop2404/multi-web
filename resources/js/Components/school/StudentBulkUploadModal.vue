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
                        <p class="text-xs text-gray-600">Download the template, fill it in Excel, Google Sheets, or any spreadsheet app, then upload the file directly &mdash; no need to convert to CSV. No row limit.</p>
                        <ul class="list-disc list-inside text-xs text-gray-600 space-y-0.5">
                            <li><strong>full_name</strong> — required</li>
                            <li><strong>class_name</strong> — required, must match your class master</li>
                            <li><strong>gender</strong> — optional (male / female / other)</li>
                            <li><strong>dob</strong> — optional (YYYY-MM-DD)</li>
                            <li><strong>email</strong> — optional parent email</li>
                        </ul>
                        <p class="text-xs text-gray-600">Keep the first row as column headers — column order doesn't matter, but the names must match the template.</p>
                    </div>

                    <p v-if="classNames.length" class="text-xs text-gray-500">
                        <span class="font-semibold text-gray-700">Valid class names:</span>
                        {{ classNames.join(', ') }}
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <a :href="templateUrl"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-[#0f3d7a] hover:underline">
                            ↓ Download Excel template (.xlsx)
                        </a>
                        <a :href="`${templateUrl}?format=csv`"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-[#0f3d7a] hover:underline">
                            ↓ Download CSV template
                        </a>
                    </div>

                    <form @submit.prevent="submitCsv" class="space-y-3">
                        <div>
                            <label class="form-label mb-1.5">CSV or Excel file *</label>
                            <input type="file" accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required @change="onCsvFile"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                            <p v-if="previewError" class="text-xs text-red-500 mt-1">{{ previewError }}</p>
                        </div>

                        <button v-if="csvFile && !previewData" type="button" class="btn-secondary text-sm"
                                :disabled="previewLoading" @click="runPreview">
                            {{ previewLoading ? 'Checking…' : 'Preview before import' }}
                        </button>

                        <p v-if="previewError" class="text-xs text-red-600">{{ previewError }}</p>

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

                        <div v-if="importErrors?.length" class="rounded-lg border border-red-100 bg-red-50 p-3 max-h-32 overflow-y-auto">
                            <p class="text-xs font-semibold text-red-700 mb-1">Import issues</p>
                            <ul class="text-xs text-red-600 space-y-0.5">
                                <li v-for="(err, i) in importErrors" :key="i">Row {{ err.row }}: {{ err.message }}</li>
                            </ul>
                        </div>

                        <FormActions>
                            <button type="button" @click="close" class="btn-ghost">Cancel</button>
                            <button type="submit" :disabled="importing || !csvFile || !classNames.length" class="btn-primary">
                                {{ importing ? 'Importing…' : 'Import CSV' }}
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
                        <p class="text-xs text-gray-600">
                            Put JPG, PNG, or WebP images in a ZIP. Copy each student's admission number from the students list
                            (e.g. <code class="font-mono">STU/26/0006</code>) and paste it as the image filename — add
                            <code class="font-mono">.jpg</code> at the end. No special renaming needed; the system matches automatically.
                        </p>
                    </div>

                    <form @submit.prevent="submitZip" class="space-y-3">
                        <div>
                            <label class="form-label mb-1.5">ZIP file *</label>
                            <input type="file" accept=".zip,application/zip" required @change="onZipFile"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]">
                        </div>

                        <p v-if="photoError" class="text-xs text-red-500">{{ photoError }}</p>

                        <FormActions>
                            <button type="button" @click="close" class="btn-ghost">Cancel</button>
                            <button type="submit" :disabled="photoUploading || !zipFile" class="btn-primary">
                                {{ photoUploading ? 'Uploading…' : 'Upload ZIP' }}
                            </button>
                        </FormActions>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
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
const importErrors = ref(null);
const importing = ref(false);
const photoUploading = ref(false);
const photoError = ref(null);
const csvFile = ref(null);
const zipFile = ref(null);
const previewData = ref(null);
const previewLoading = ref(false);
const previewError = ref(null);

watch(() => props.modelValue, (open) => {
    if (open) activeTab.value = props.initialTab;
    if (!open) {
        previewData.value = null;
        previewError.value = null;
        csvFile.value = null;
        zipFile.value = null;
        importErrors.value = null;
        photoError.value = null;
    }
});

function close() {
    emit('update:modelValue', false);
}

function onCsvFile(e) {
    csvFile.value = e.target.files[0] ?? null;
    previewData.value = null;
    previewError.value = null;
    importErrors.value = null;
}

async function runPreview() {
    if (!csvFile.value) return;
    previewLoading.value = true;
    previewData.value = null;
    previewError.value = null;
    const formData = new FormData();
    formData.append('file', csvFile.value);
    try {
        const res = await fetch(`/school-admin/${props.schoolId}/students/import/preview`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: formData,
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => null);
        if (!res.ok) {
            previewError.value = data?.errors?.file?.[0]
                ?? data?.message
                ?? 'Preview failed. Choose the file again and retry.';
            return;
        }
        previewData.value = data;
        importErrors.value = null;
    } catch {
        previewError.value = 'Preview failed. Check your connection and try again.';
    } finally {
        previewLoading.value = false;
    }
}

function onZipFile(e) {
    zipFile.value = e.target.files[0] ?? null;
    photoError.value = null;
}

function submitCsv() {
    if (!csvFile.value || importing.value) return;

    importing.value = true;
    importErrors.value = null;

    const formData = new FormData();
    formData.append('file', csvFile.value);

    router.post(`/school-admin/${props.schoolId}/students/import`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importing.value = false;
        },
        onSuccess: () => {
            const result = usePage().props.flash?.importResult;
            if (result?.errors?.length) {
                importErrors.value = result.errors;
                return;
            }
            csvFile.value = null;
            previewData.value = null;
            close();
        },
        onError: (errors) => {
            importErrors.value = errors.file
                ? [{ row: 0, message: errors.file }]
                : [{ row: 0, message: 'Import failed. Choose the file again and retry.' }];
        },
    });
}

function submitZip() {
    if (photoUploading.value || !zipFile.value) return;

    photoUploading.value = true;
    photoError.value = null;

    const formData = new FormData();
    formData.append('zip', zipFile.value);

    router.post(`/school-admin/${props.schoolId}/students/photos-zip`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            photoUploading.value = false;
        },
        onSuccess: () => {
            zipFile.value = null;
            close();
            router.reload({ only: ['students', 'unverifiedCount'] });
        },
        onError: (errors) => {
            photoError.value = errors.zip ?? 'Photo upload failed. Choose the ZIP file again and retry.';
        },
    });
}
</script>
