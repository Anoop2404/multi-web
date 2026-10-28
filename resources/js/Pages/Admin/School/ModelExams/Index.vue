<template>
    <SchoolAdminLayout title="Model exams" :school="school" :show-header-title="false">
        <PageHeader title="Model exams" eyebrow="Exams & training"
                    description="Create class- or category-scoped MCQ model exams for your school. No Talent Search fees or hall tickets.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/model-exams/question-banks`" class="btn-secondary text-sm">
                    Question banks ({{ banksCount }})
                </Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.exams }}</p>
                <p class="text-xs text-slate-500 mt-1">Exams</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.active }}</p>
                <p class="text-xs text-slate-500 mt-1">Open</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Enrolled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.published }}</p>
                <p class="text-xs text-slate-500 mt-1">Results released</p>
            </div>
        </div>

        <form @submit.prevent="createExam" class="card mb-6 space-y-4">
            <h3 class="section-title !mb-0">New model exam</h3>
            <FormGrid>
                <FormField label="Exam title" class-extra="sm:col-span-2" required>
                    <input v-model="form.title" class="field" placeholder="e.g. Class 8 Science model exam" required>
                </FormField>
                <FormField label="Code" hint="Optional">
                    <input v-model="form.code" class="field font-mono uppercase" maxlength="64" placeholder="e.g. MOD-SCI-8">
                </FormField>
                <FormField label="Duration (min)">
                    <input v-model.number="form.duration_minutes" type="number" min="5" max="480" class="field" placeholder="60">
                </FormField>
                <FormField label="Scheduled start">
                    <input v-model="form.scheduled_at" type="datetime-local" class="field">
                </FormField>
                <FormField label="Delivery">
                    <SearchableSelect v-model="form.delivery_mode"
                                       :options="[{ value: 'online', label: 'Online (student portal)' }, { value: 'offline', label: 'Offline' }]"
                                       :all-option="false" />
                </FormField>
                <FormField label="Grade master">
                    <SearchableSelect v-model="form.grade_master_id" :options="gradeMasterOptions" placeholder="Optional" />
                </FormField>
            </FormGrid>
            <div class="border-t border-slate-100 pt-4">
                <McqEligibilityPicker mode="school_model" v-model="form.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Create exam</button>
            </FormActions>
        </form>

        <div class="card overflow-hidden p-0">
            <EmptyState v-if="!exams.length" title="No model exams yet"
                        description="Create an exam above, add a Ready question bank, then publish and enroll students."
                        icon="📝" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Schedule</th>
                        <th>Delivery</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th>Enrolled</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="exam in exams" :key="exam.id">
                        <td class="font-medium text-slate-900">{{ exam.title }}</td>
                        <td class="text-xs text-slate-600 whitespace-nowrap">{{ formatSchedule(exam.scheduled_at) }}</td>
                        <td class="text-xs capitalize">{{ exam.delivery_mode || 'online' }}</td>
                        <td class="text-xs text-slate-600 max-w-[180px]">{{ exam.eligibility_summary || 'All classes' }}</td>
                        <td>
                            <span class="status-pill capitalize" :class="statusClass(exam.status)">{{ exam.status }}</span>
                            <span v-if="exam.results_published" class="ml-1 text-[11px] text-emerald-700">· results out</span>
                        </td>
                        <td>{{ exam.registrations_count ?? 0 }}</td>
                        <td class="text-right">
                            <Link :href="`/school-admin/${school.id}/model-exams/${exam.id}`" class="link-brand text-xs font-semibold">Open →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';

const props = defineProps({
    school: Object,
    exams: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    gradeMasters: { type: Array, default: () => [] },
    banksCount: { type: Number, default: 0 },
});

const gradeMasterOptions = computed(() => [
    { value: null, label: 'None' },
    ...props.gradeMasters.map((m) => ({
        value: m.id,
        label: m.is_default ? `${m.title} (default)` : m.title,
    })),
]);

const form = useForm({
    title: '',
    code: '',
    duration_minutes: 60,
    scheduled_at: '',
    delivery_mode: 'online',
    grade_master_id: null,
    eligibility_config: {
        audience: 'students',
        assignment_type: 'all',
        class_category_ids: [],
        master_class_ids: [],
        class_groups: [],
        gender: 'open',
    },
    shuffle_questions: true,
    shuffle_options: true,
    multi_marking: 'partial',
});

function createExam() {
    form.post(`/school-admin/${props.school.id}/model-exams`, { preserveScroll: true });
}

function formatSchedule(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function statusClass(status) {
    if (status === 'published' || status === 'ongoing') return 'status-pill--success';
    if (status === 'draft') return 'status-pill--neutral';
    if (status === 'cancelled') return 'status-pill--danger';
    return 'status-pill--info';
}
</script>
