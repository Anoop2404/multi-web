<template>
    <SahodayaAdminLayout title="MCQ Exams" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="All MCQ exams" eyebrow="MCQ exams"
                    description="Create standalone Level 1 exams or use exam series for multi-level promotion.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq`" class="btn-secondary text-sm">← MCQ dashboard</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-series`" class="btn-secondary text-sm">Exam series</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.exams }}</p>
                <p class="text-xs text-slate-500 mt-1">Exams</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.active }}</p>
                <p class="text-xs text-slate-500 mt-1">Active / scheduled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.published }}</p>
                <p class="text-xs text-slate-500 mt-1">Results published</p>
            </div>
        </div>

        <form @submit.prevent="createExam" class="card mb-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="section-title !mb-0">Add standalone exam (Level 1)</h3>
                <p class="text-xs text-slate-500">For multi-level, use <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-series`" class="link-brand">Exam series</Link>.</p>
            </div>
            <FormGrid>
                <FormField label="Exam title" class-extra="sm:col-span-2" required>
                    <input v-model="form.title" class="field" placeholder="e.g. Sahodaya MCQ 2026" required>
                </FormField>
                <FormField label="Type">
                    <select v-model="form.exam_type" class="field">
                        <option value="assessment">Assessment</option>
                        <option value="practice">Practice</option>
                        <option value="competitive">Competitive</option>
                    </select>
                </FormField>
                <FormField label="Duration (min)">
                    <input v-model.number="form.duration_minutes" type="number" min="5" max="480" class="field" placeholder="60">
                </FormField>
                <FormField label="Scheduled date & time">
                    <input v-model="form.scheduled_at" type="datetime-local" class="field">
                </FormField>
                <FormField label="Delivery mode">
                    <select v-model="form.delivery_mode" class="field">
                        <option value="offline">Offline (paper / venue)</option>
                        <option value="online">Online (student portal)</option>
                    </select>
                </FormField>
                <FormField label="Per-student fee (₹)" hint="Optional while draft">
                    <input v-model.number="form.fee_amount" type="number" min="0" step="0.01" class="field" placeholder="0">
                </FormField>
            </FormGrid>
            <div class="border-t border-slate-100 pt-4">
                <McqEligibilityPicker v-model="form.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Create exam</button>
            </FormActions>
        </form>

        <div class="card overflow-hidden p-0">
            <EmptyState v-if="!exams.length" title="No MCQ exams yet" description="Create your first exam using the form above." icon="📝" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Schedule</th>
                        <th>Fee</th>
                        <th>Level</th>
                        <th>Delivery</th>
                        <th>Classes</th>
                        <th>Series</th>
                        <th>Status</th>
                        <th>Reg.</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="exam in exams" :key="exam.id">
                        <td class="font-medium text-slate-900">{{ exam.title }}</td>
                        <td class="text-xs text-slate-600 whitespace-nowrap">{{ formatSchedule(exam.scheduled_at) }}</td>
                        <td class="text-xs whitespace-nowrap" :class="formatFee(exam) === 'Free' ? 'text-slate-400' : 'font-semibold text-emerald-700'">
                            {{ formatFee(exam) }}
                        </td>
                        <td class="text-xs">
                            <span class="font-semibold text-indigo-700">{{ exam.level_label || 'Level 1' }}</span>
                        </td>
                        <td class="text-xs capitalize">{{ exam.delivery_mode || 'offline' }}</td>
                        <td class="text-xs text-slate-600 max-w-[180px]">{{ exam.eligibility_summary || 'All classes' }}</td>
                        <td class="text-xs text-slate-500">{{ exam.series_title || '—' }}</td>
                        <td>
                            <span class="status-pill capitalize" :class="statusClass(exam.status)">{{ exam.status }}</span>
                        </td>
                        <td>{{ exam.registrations_count ?? 0 }}</td>
                        <td class="text-right whitespace-nowrap space-x-2">
                            <button type="button" class="text-xs font-semibold text-indigo-600 hover:underline" @click="openEdit(exam)">Edit</button>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}`" class="link-brand text-xs font-semibold">Open →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editingExam" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeEdit">
            <form @submit.prevent="saveEdit" class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="section-title !mb-0">Edit exam</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none" @click="closeEdit">×</button>
                </div>
                <FormGrid>
                    <FormField label="Exam title" class-extra="sm:col-span-2" required>
                        <input v-model="editForm.title" class="field" required>
                    </FormField>
                    <FormField label="Type">
                        <select v-model="editForm.exam_type" class="field">
                            <option value="assessment">Assessment</option>
                            <option value="practice">Practice</option>
                            <option value="competitive">Competitive</option>
                        </select>
                    </FormField>
                    <FormField label="Status">
                        <select v-model="editForm.status" class="field">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </FormField>
                    <FormField label="Scheduled date & time">
                        <input v-model="editForm.scheduled_at" type="datetime-local" class="field">
                    </FormField>
                    <FormField label="Duration (min)">
                        <input v-model.number="editForm.duration_minutes" type="number" min="5" max="480" class="field">
                    </FormField>
                    <FormField label="Delivery mode">
                        <select v-model="editForm.delivery_mode" class="field">
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                        </select>
                    </FormField>
                    <FormField label="Per-student fee (₹)" class-extra="sm:col-span-2" hint="Required when status is Published or Ongoing">
                        <input v-model.number="editForm.fee_amount" type="number" min="0" step="0.01" class="field">
                    </FormField>
                </FormGrid>
                <div class="border-t border-slate-100 pt-4">
                    <h4 class="text-sm font-semibold mb-2">Class / category assignment</h4>
                    <McqEligibilityPicker v-model="editForm.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
                </div>
                <p v-if="editForm.errors.fee_amount" class="text-xs text-red-600">{{ editForm.errors.fee_amount }}</p>
                <FormActions>
                    <button type="button" class="btn-secondary" @click="closeEdit">Cancel</button>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${editingExam.id}`" class="btn-secondary">Open full exam →</Link>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save changes</button>
                </FormActions>
            </form>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exams: Array,
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ exams: 0, active: 0, registrations: 0, published: 0 }) },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams`;

const defaultEligibility = () => ({
    scope: 'all',
    assignment_type: 'all',
    class_category_ids: [],
    master_class_ids: [],
    class_groups: [],
    gender: 'open',
});

const form = useForm({
    title: '',
    exam_type: 'assessment',
    delivery_mode: 'offline',
    scheduled_at: '',
    duration_minutes: 60,
    fee_amount: null,
    eligibility_config: defaultEligibility(),
});

const editingExam = ref(null);
const editForm = useForm({
    title: '',
    exam_type: 'assessment',
    status: 'draft',
    delivery_mode: 'offline',
    scheduled_at: '',
    duration_minutes: 60,
    fee_amount: null,
    eligibility_config: defaultEligibility(),
});

function toDatetimeLocal(value) {
    if (!value) return '';
    return String(value).slice(0, 16);
}

function formatSchedule(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function formatFee(exam) {
    const amount = Number(exam?.fee_amount);
    if (!Number.isFinite(amount) || amount <= 0 || exam?.fee_type === 'none') {
        return 'Free';
    }
    return amount % 1 === 0 ? `₹${amount}` : `₹${amount.toFixed(2)}`;
}

function statusClass(status) {
    if (status === 'published' || status === 'ongoing') return 'status-pill--published';
    if (status === 'completed') return 'status-pill--success';
    return 'status-pill--draft';
}

function createExam() {
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => form.reset({
            exam_type: 'assessment',
            delivery_mode: 'offline',
            scheduled_at: '',
            duration_minutes: 60,
            fee_amount: null,
            eligibility_config: defaultEligibility(),
        }),
    });
}

function openEdit(exam) {
    editingExam.value = exam;
    editForm.clearErrors();
    editForm.title = exam.title;
    editForm.exam_type = exam.exam_type ?? 'assessment';
    editForm.status = exam.status ?? 'draft';
    editForm.delivery_mode = exam.delivery_mode ?? 'offline';
    editForm.scheduled_at = toDatetimeLocal(exam.scheduled_at);
    editForm.duration_minutes = exam.duration_minutes ?? 60;
    editForm.fee_amount = exam.fee_amount ?? null;
    const ec = exam.eligibility_config ?? {};
    editForm.eligibility_config = {
        scope: ec.scope ?? 'all',
        assignment_type: ec.assignment_type
            ?? (ec.class_category_ids?.length ? 'category' : (ec.master_class_ids?.length ? 'class' : 'all')),
        class_category_ids: [...(ec.class_category_ids ?? [])],
        master_class_ids: [...(ec.master_class_ids ?? [])],
        class_groups: [...(ec.class_groups ?? [])],
        gender: ec.gender ?? 'open',
    };
}

function closeEdit() {
    editingExam.value = null;
}

function saveEdit() {
    editForm.put(`${base}/${editingExam.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}
</script>
