<template>
    <SchoolAdminLayout title="Question Papers" :school="school">
        <PageHeader title="Question Papers" eyebrow="Academics"
                    description="View and download question papers uploaded by teachers across all classes and subjects." />

        <form class="card !p-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-5" @submit.prevent="applyFilters">
            <select v-model="filterForm.school_class_id" class="field" aria-label="Filter by class">
                <option value="">All classes</option>
                <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
            <select v-model="filterForm.subject_id" class="field" aria-label="Filter by subject">
                <option value="">All subjects</option>
                <option v-for="item in subjects" :key="item.id" :value="item.id">{{ item.label }}</option>
            </select>
            <select v-model="filterForm.teacher_id" class="field" aria-label="Filter by teacher">
                <option value="">All teachers</option>
                <option v-for="item in teachers" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
            <select v-model="filterForm.academic_year" class="field" aria-label="Filter by academic year">
                <option value="">All years</option>
                <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
            </select>
            <div class="flex gap-2">
                <input v-model="filterForm.search" class="field flex-1 min-w-0" type="search" placeholder="Search papers" aria-label="Search papers">
                <button class="btn-primary">Filter</button>
            </div>
            <button v-if="hasFilters" type="button" class="text-sm text-slate-600 w-fit" @click="clearFilters">Clear filters</button>
        </form>

        <section class="card card--flush overflow-x-auto">
            <table v-if="papers.data.length" class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="p-3">Paper</th>
                        <th class="p-3">Class</th>
                        <th class="p-3">Subject</th>
                        <th class="p-3">Teacher</th>
                        <th class="p-3">Year</th>
                        <th class="p-3">Uploaded</th>
                        <th class="p-3 text-right">File</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="paper in papers.data" :key="paper.id" class="border-t border-slate-100">
                        <td class="p-3 min-w-64">
                            <p class="font-semibold text-slate-900">{{ paper.title }}</p>
                            <p v-if="paper.exam_name" class="text-xs text-slate-500 mt-0.5">{{ paper.exam_name }}</p>
                        </td>
                        <td class="p-3 text-slate-700">{{ paper.school_class?.name || paper.class_name || '—' }}</td>
                        <td class="p-3 text-slate-700">{{ paper.subject_name }}</td>
                        <td class="p-3 text-slate-700">{{ paper.teacher?.name || 'Former teacher' }}</td>
                        <td class="p-3 text-slate-600">{{ paper.academic_year }}</td>
                        <td class="p-3 text-slate-500 whitespace-nowrap">{{ formatDate(paper.created_at) }}</td>
                        <td class="p-3 text-right">
                            <div class="flex flex-col items-end gap-1">
                                <div v-for="file in paper.files" :key="file.id" class="text-xs whitespace-nowrap">
                                    <span class="text-slate-500">{{ file.original_name }} ({{ fileSize(file.file_size) }})</span>
                                    <a v-if="isPreviewable(file)" :href="`/school-admin/${school.id}/question-papers/${paper.id}/files/${file.id}/preview`"
                                       target="_blank" rel="noopener" class="text-indigo-700 font-semibold ml-1">Preview</a>
                                    <a :href="`/school-admin/${school.id}/question-papers/${paper.id}/files/${file.id}/download`"
                                       class="text-indigo-700 font-semibold ml-1">Download</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else title="No question papers found"
                        description="Teacher uploads will appear here. Adjust the filters if you are looking for a specific paper." icon="📄" class="py-12" />
        </section>

        <div v-if="papers.links?.length > 3" class="flex justify-center gap-1 mt-5">
            <Link v-for="link in papers.links" :key="link.label" :href="link.url || '#'" preserve-scroll preserve-state
                  class="px-3 py-1.5 rounded text-xs font-medium"
                  :class="link.active ? 'bg-[#041525] text-white' : (link.url ? 'bg-white text-slate-700 border' : 'text-slate-300 pointer-events-none')"
                  v-html="link.label" />
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    school: Object,
    papers: Object,
    filters: Object,
    classes: Array,
    subjects: Array,
    teachers: Array,
    academicYears: Array,
});

const filterForm = reactive({
    school_class_id: props.filters?.school_class_id || '',
    subject_id: props.filters?.subject_id || '',
    teacher_id: props.filters?.teacher_id || '',
    academic_year: props.filters?.academic_year || '',
    search: props.filters?.search || '',
});

const hasFilters = computed(() => Object.values(filterForm).some(Boolean));

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/question-papers`, filterForm, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    Object.assign(filterForm, { school_class_id: '', subject_id: '', teacher_id: '', academic_year: '', search: '' });
    applyFilters();
}

function fileSize(bytes) {
    const value = Number(bytes || 0);
    if (!value) return 'Size unavailable';
    if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function isPreviewable(file) {
    const mime = file.mime_type || '';
    return mime.startsWith('image/') || mime === 'application/pdf';
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : '—';
}
</script>
