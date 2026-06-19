<template>
    <SchoolAdminLayout title="Import Students" :school="school">
        <div class="max-w-2xl space-y-6">
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 text-sm text-blue-900 space-y-2">
                <p class="font-semibold">Bulk import from CSV</p>
                <p>Upload a spreadsheet with three columns only:</p>
                <ul class="list-disc list-inside text-blue-800/90 space-y-1">
                    <li><strong>full_name</strong> — student’s full name (required)</li>
                    <li><strong>class_name</strong> — must match your Sahodaya class list (required)</li>
                    <li><strong>email</strong> — optional contact email</li>
                </ul>
                <p class="text-xs text-blue-700/80">Each student gets a Sahodaya registration number automatically (e.g. MLCS/AMU/27/0001).</p>
            </div>

            <div v-if="classNames.length" class="bg-white rounded-xl border border-gray-100 p-5 text-sm">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Your class names</p>
                <p class="text-gray-600">{{ classNames.join(', ') }}</p>
                <p class="text-xs text-gray-400 mt-2">
                    Class names in the CSV must match exactly (e.g. <span class="font-mono">10</span>, not <span class="font-mono">Class 10</span>).
                    Classes are set by your Sahodaya.
                </p>
            </div>

            <div v-else class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No classes are available yet. Contact your Sahodaya admin — classes are configured centrally.
            </div>

            <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                <a :href="`/school-admin/${school.id}/students/import/template`"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:underline">
                    ↓ Download CSV template
                </a>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">CSV file *</label>
                        <input type="file" accept=".csv,text/csv" required @change="onFile"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700">
                        <p v-if="form.errors.file" class="text-xs text-red-500 mt-1">{{ form.errors.file }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing || !classNames.length"
                                class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                            Import students
                        </button>
                        <Link :href="`/school-admin/${school.id}/students`" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
                    </div>
                </form>
            </section>

            <div v-if="importResult?.errors?.length" class="bg-white rounded-xl border border-red-100 p-5 space-y-2">
                <p class="text-sm font-semibold text-red-700">Import issues</p>
                <ul class="text-xs text-red-600 space-y-1 max-h-48 overflow-y-auto">
                    <li v-for="(err, i) in importResult.errors" :key="i">
                        Row {{ err.row }}: {{ err.message }}
                    </li>
                </ul>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    school:     Object,
    classNames: { type: Array, default: () => [] },
});

const page = usePage();
const importResult = computed(() => page.props.flash?.importResult ?? null);

const form = useForm({ file: null });

function onFile(event) {
    form.file = event.target.files[0] ?? null;
}

function submit() {
    form.post(`/school-admin/${props.school.id}/students/import`, { forceFormData: true });
}
</script>
