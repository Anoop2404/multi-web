<template>
    <section class="card space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="section-title !mb-1">Import from Excel</h3>
                <p class="text-sm text-slate-500">
                    Fill the template (one question per row: type, question, options A–F, answer, marks). Text can include
                    Malayalam, symbols and <code>$…$</code> formulas. Add images afterwards in the editor.
                </p>
            </div>
            <a :href="templateUrl" class="btn-secondary text-sm">Download template</a>
        </div>

        <form v-if="!preview" class="flex flex-wrap items-center gap-3" @submit.prevent="upload">
            <input type="file" accept=".xlsx,.csv" class="text-sm" @change="uploadForm.file = $event.target.files?.[0] ?? null">
            <button type="submit" class="btn-primary text-sm" :disabled="!uploadForm.file || uploadForm.processing || locked">
                {{ uploadForm.processing ? 'Reading…' : 'Check file' }}
            </button>
            <InputError :message="uploadForm.errors.file" class="w-full" />
        </form>

        <div v-else class="space-y-3">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="font-semibold">{{ preview.filename }}</span>
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ preview.valid_count }} ready</span>
                <span v-if="preview.error_count" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">{{ preview.error_count }} with problems</span>
            </div>

            <div class="max-h-96 overflow-auto rounded-lg border border-slate-200">
                <table class="data-table text-xs">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Type</th>
                            <th>Question</th>
                            <th>Answer</th>
                            <th>Marks</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in preview.rows" :key="row.row" :class="row.errors.length ? 'bg-red-50/60' : ''">
                            <td>{{ row.row }}</td>
                            <td class="whitespace-nowrap">{{ typeLabels[row.type] ?? row.type }}</td>
                            <td class="min-w-[16rem]"><McqRichText :text="row.question" /></td>
                            <td>{{ row.answer }}</td>
                            <td>{{ row.marks }}</td>
                            <td class="min-w-[12rem]">
                                <span v-if="!row.errors.length" class="font-semibold text-emerald-700">OK</span>
                                <ul v-else class="list-disc pl-4 text-red-700">
                                    <li v-for="(error, i) in row.errors" :key="i">{{ error }}</li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="preview.error_count" class="text-xs text-slate-500">
                Rows with problems are skipped. Fix them in the file and import it again, or add them by hand.
            </p>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary text-sm" :disabled="!preview.valid_count || commitForm.processing" @click="commit">
                    Import {{ preview.valid_count }} question{{ preview.valid_count === 1 ? '' : 's' }}
                </button>
                <button type="button" class="btn-secondary text-sm" @click="dismissed = preview.token">Choose another file</button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import McqRichText from '@/Components/mcq/McqRichText.vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    bankId: { type: Number, required: true },
    typeLabels: { type: Object, required: true },
    locked: { type: Boolean, default: false },
});

const page = usePage();
const dismissed = ref(null);
const preview = computed(() => {
    const flash = page.props.flash?.mcqImportPreview;
    return flash && flash.token !== dismissed.value ? flash : null;
});

const templateUrl = computed(() => `${props.baseUrl}/import-template`);
const uploadForm = useForm({ file: null });
const commitForm = useForm({ token: '' });

function upload() {
    uploadForm.post(`${props.baseUrl}/${props.bankId}/import/preview`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset(),
    });
}

function commit() {
    commitForm.token = preview.value.token;
    commitForm.post(`${props.baseUrl}/${props.bankId}/import/commit`, {
        preserveScroll: true,
        onSuccess: () => { dismissed.value = commitForm.token; },
    });
}
</script>
