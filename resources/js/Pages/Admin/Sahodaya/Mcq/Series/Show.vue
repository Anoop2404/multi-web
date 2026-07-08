<template>
    <SahodayaAdminLayout :title="series.title" :sahodaya="sahodaya">
        <PageHeader :title="series.title" eyebrow="Talent Search series"
                    description="Create level-based exams with class/category assignment, dates, and direct links to manage each level.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-series`" class="btn-secondary text-sm">← Series list</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq`" class="btn-secondary text-sm">All exams</Link>
            </template>
        </PageHeader>

        <section class="card mb-6 overflow-hidden !p-0">
            <div class="p-4 border-b border-slate-100">
                <h2 class="section-title !mb-0">Exam levels</h2>
                <p class="section-desc">Each level opens in its own exam workspace — registrations, fees, hall tickets, results.</p>
            </div>
            <EmptyState v-if="!series.exams?.length" title="No levels yet" description="Add Level 1 and Level 2 below, or add one level at a time." icon="📚" class="py-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Title</th>
                            <th>Schedule</th>
                            <th>Fee</th>
                            <th>Class / category</th>
                            <th>Promotion</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="exam in series.exams" :key="exam.id">
                            <td><span class="font-semibold text-indigo-700">{{ exam.level_label || `L${exam.exam_level}` }}</span></td>
                            <td class="font-medium">{{ exam.title }}</td>
                            <td class="text-xs whitespace-nowrap">{{ formatSchedule(exam.scheduled_at) }}</td>
                            <td class="text-xs whitespace-nowrap" :class="formatFee(exam) === 'Free' ? 'text-slate-400' : 'font-semibold text-emerald-700'">
                                {{ formatFee(exam) }}
                            </td>
                            <td class="text-xs text-slate-600 max-w-[200px]">{{ exam.eligibility_summary || 'All classes' }}</td>
                            <td class="text-xs text-slate-600">{{ (exam.exam_level ?? 1) > 1 ? (exam.eligibility_mode_label || exam.eligibility_mode) : 'Open registration' }}</td>
                            <td><span class="status-pill capitalize">{{ exam.status }}</span></td>
                            <td class="text-right whitespace-nowrap">
                                <Link :href="exam.exam_url" class="link-brand text-xs font-semibold">Open exam →</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-for="exam in promotionExams" :key="'promo-'+exam.id" class="card mb-6 overflow-hidden !p-0">
            <div class="p-4 border-b border-slate-100 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="section-title !mb-0">Promotion — {{ exam.title }}</h2>
                    <p class="section-desc">
                        After {{ exam.promotion?.parent_title || 'parent level' }} results are published,
                        lock the qualifier list so only promoted students can register.
                    </p>
                </div>
                <span v-if="exam.promotion?.promotion_locked" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800">
                    List locked ({{ exam.promotion.promoted_count }} students)
                </span>
            </div>
            <div class="p-4 space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-lg bg-slate-50 p-3 text-center">
                        <p class="text-lg font-bold text-indigo-700">{{ exam.promotion?.qualifier_count ?? 0 }}</p>
                        <p class="text-xs text-slate-500">Currently qualify</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-center">
                        <p class="text-lg font-bold">{{ exam.promotion?.parent_published ? 'Yes' : 'No' }}</p>
                        <p class="text-xs text-slate-500">Parent results published</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-center col-span-2">
                        <p class="text-sm font-medium">{{ exam.eligibility_mode_label }}</p>
                        <p class="text-xs text-slate-500">Promotion rule</p>
                    </div>
                </div>
                <div v-if="exam.promotion?.qualifiers?.length" class="overflow-x-auto border border-slate-100 rounded-lg">
                    <table class="data-table">
                        <thead><tr><th>Rank</th><th>Student</th><th>School</th><th>Score</th></tr></thead>
                        <tbody>
                            <tr v-for="q in exam.promotion.qualifiers.slice(0, 20)" :key="q.student_id">
                                <td>{{ q.rank ?? '—' }}</td>
                                <td>{{ q.student_name }} <span class="text-slate-400 text-xs">{{ q.reg_no }}</span></td>
                                <td class="text-xs">{{ q.school_name }}</td>
                                <td>{{ q.score ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="exam.promotion.qualifier_count > 20" class="text-xs text-slate-500 p-2">Showing 20 of {{ exam.promotion.qualifier_count }} qualifiers.</p>
                </div>
                <EmptyState v-else-if="!exam.promotion?.parent_published" title="Waiting for parent results"
                            description="Publish Level 1 results before running promotion." icon="⏳" class="py-6" />
                <EmptyState v-else title="No qualifiers yet" description="No students meet the promotion rule from the parent exam." icon="📋" class="py-6" />
                <div class="flex flex-wrap gap-2">
                    <button v-if="exam.promotion?.can_promote" type="button" class="btn-primary text-sm" @click="lockPromotion(exam.id)">
                        Lock promotion list ({{ exam.promotion.qualifier_count }} students)
                    </button>
                    <Link :href="exam.exam_url" class="btn-secondary text-sm">Open exam workspace →</Link>
                </div>
            </div>
        </section>

        <form v-if="!series.exams?.length" @submit.prevent="createTwoLevels" class="card mb-6 space-y-5">
            <div>
                <h2 class="section-title">Quick setup — Level 1 &amp; Level 2</h2>
                <p class="section-desc">Create both exams with dates in one step. Level 2 promotes students who meet the cutoff from Level 1.</p>
            </div>

            <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-indigo-800">Who can register (both levels)</h3>
                <McqEligibilityPicker v-model="bulkForm.shared_eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                    <h3 class="text-sm font-semibold">Level 1</h3>
                    <input v-model="bulkForm.level1.title" class="field" placeholder="Exam title" required>
                    <input v-model="bulkForm.level1.scheduled_at" type="datetime-local" class="field">
                    <input v-model.number="bulkForm.level1.duration_minutes" type="number" min="5" class="field" placeholder="Duration (min)">
                    <input v-model.number="bulkForm.level1.fee_amount" type="number" min="0" step="0.01" class="field" placeholder="Student fee (₹)">
                    <input v-model.number="bulkForm.level1.school_discount_amount" type="number" min="0" step="0.01" class="field" placeholder="School discount (₹)">
                </div>
                <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                    <h3 class="text-sm font-semibold">Level 2</h3>
                    <input v-model="bulkForm.level2.title" class="field" placeholder="Exam title" required>
                    <input v-model="bulkForm.level2.scheduled_at" type="datetime-local" class="field">
                    <input v-model.number="bulkForm.level2.duration_minutes" type="number" min="5" class="field" placeholder="Duration (min)">
                    <input v-model.number="bulkForm.level2.fee_amount" type="number" min="0" step="0.01" class="field" placeholder="Student fee (₹)">
                    <input v-model.number="bulkForm.level2.school_discount_amount" type="number" min="0" step="0.01" class="field" placeholder="School discount (₹)">
                    <input v-model.number="bulkForm.level2.cutoff_score" type="number" min="0" step="0.01" class="field" placeholder="Min score from Level 1">
                </div>
            </div>

            <button type="submit" class="btn-primary" :disabled="bulkForm.processing">Create Level 1 &amp; Level 2</button>
        </form>

        <form @submit.prevent="addLevel" class="card space-y-4">
            <h2 class="section-title">Add one level</h2>
            <FormGrid>
                <FormField label="Exam title" class-extra="sm:col-span-2" required>
                    <input v-model="levelForm.title" class="field" placeholder="e.g. Sahodaya Talent Search Level 1" required>
                </FormField>
                <FormField label="Level">
                    <input v-model.number="levelForm.exam_level" type="number" min="1" max="10" class="field">
                </FormField>
                <FormField label="Scheduled date & time">
                    <input v-model="levelForm.scheduled_at" type="datetime-local" class="field">
                </FormField>
                <FormField label="Duration (min)">
                    <input v-model.number="levelForm.duration_minutes" type="number" min="5" max="480" class="field">
                </FormField>
                <FormField label="Per-student fee (₹)" hint="Student fee collected by the school">
                    <input v-model.number="levelForm.fee_amount" type="number" min="0" step="0.01" class="field">
                </FormField>
                <FormField label="School discount (₹)" hint="Sahodaya discount — school remits student fee minus this amount">
                    <input v-model.number="levelForm.school_discount_amount" type="number" min="0" step="0.01" class="field">
                </FormField>
                <FormField v-if="levelForm.exam_level > 1" label="Parent exam" class-extra="sm:col-span-2">
                    <select v-model="levelForm.parent_exam_id" class="field" :required="levelForm.exam_level > 1">
                        <option value="">Select completed parent exam</option>
                        <option v-for="e in parentOptions" :key="e.id" :value="e.id">{{ e.title }} (L{{ e.exam_level }})</option>
                    </select>
                </FormField>
                <FormField v-if="levelForm.exam_level > 1" label="Promotion rule" class-extra="sm:col-span-2">
                    <select v-model="levelForm.eligibility_mode" class="field">
                        <option value="open">Open (class/gender only)</option>
                        <option value="cutoff_marks">Minimum score from parent</option>
                        <option value="top_rank">Top N ranks from parent</option>
                        <option value="manual">Manual student list</option>
                    </select>
                </FormField>
                <FormField v-if="levelForm.exam_level > 1 && levelForm.eligibility_mode === 'cutoff_marks'" label="Cutoff score">
                    <input v-model="levelForm.cutoff_score" type="number" step="0.01" class="field">
                </FormField>
                <FormField v-if="levelForm.exam_level > 1 && levelForm.eligibility_mode === 'top_rank'" label="Top N ranks">
                    <input v-model="levelForm.top_rank_count" type="number" min="1" class="field">
                </FormField>
                <FormField label="Delivery mode" class-extra="sm:col-span-2">
                    <select v-model="levelForm.delivery_mode" class="field">
                        <option value="offline">Offline (paper / venue)</option>
                        <option value="online">Online (student portal)</option>
                    </select>
                </FormField>
            </FormGrid>

            <div class="border-t border-slate-100 pt-4">
                <h3 class="text-sm font-semibold mb-2">Class / category assignment</h3>
                <McqEligibilityPicker v-model="levelForm.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>

            <FormActions>
                <button type="submit" class="btn-primary" :disabled="levelForm.processing">Add level</button>
            </FormActions>
        </form>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';

const props = defineProps({
    sahodaya: Object,
    series: Object,
    parentExams: Array,
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
});

const defaultEligibility = () => ({
    scope: 'all',
    assignment_type: 'all',
    class_category_ids: [],
    master_class_ids: [],
    class_groups: [],
    gender: 'open',
});

const levelForm = useForm({
    title: '',
    exam_level: 1,
    parent_exam_id: '',
    eligibility_mode: 'open',
    cutoff_score: '',
    top_rank_count: '',
    scheduled_at: '',
    duration_minutes: 60,
    fee_amount: null,
    school_discount_amount: null,
    delivery_mode: 'offline',
    eligibility_config: defaultEligibility(),
});

const bulkForm = useForm({
    shared_eligibility_config: defaultEligibility(),
    level1: { title: '', scheduled_at: '', duration_minutes: 60, fee_amount: null, school_discount_amount: null },
    level2: { title: '', scheduled_at: '', duration_minutes: 60, fee_amount: null, school_discount_amount: null, cutoff_score: 60 },
});

const parentOptions = computed(() => {
    const inSeries = (props.series.exams ?? []).filter((e) => e.status === 'completed' && e.results_published);
    return [...inSeries, ...(props.parentExams ?? [])];
});

const promotionExams = computed(() =>
    (props.series.exams ?? []).filter((e) => (e.exam_level ?? 1) > 1 && e.promotion),
);

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

function addLevel() {
    levelForm.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-series/${props.series.id}/levels`);
}

function lockPromotion(examId) {
    if (!confirm('Lock the promotion list? Only listed students will be able to register for this level.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-series/${props.series.id}/exams/${examId}/promotion/lock`, {}, { preserveScroll: true });
}

function createTwoLevels() {
    bulkForm.transform((data) => ({
        shared_eligibility_config: data.shared_eligibility_config,
        levels: [
            {
                exam_level: 1,
                title: data.level1.title,
                scheduled_at: data.level1.scheduled_at || null,
                duration_minutes: data.level1.duration_minutes || 60,
                fee_amount: data.level1.fee_amount,
                school_discount_amount: data.level1.school_discount_amount,
                eligibility_mode: 'open',
                delivery_mode: 'offline',
            },
            {
                exam_level: 2,
                title: data.level2.title,
                scheduled_at: data.level2.scheduled_at || null,
                duration_minutes: data.level2.duration_minutes || 60,
                fee_amount: data.level2.fee_amount,
                school_discount_amount: data.level2.school_discount_amount,
                eligibility_mode: 'cutoff_marks',
                cutoff_score: data.level2.cutoff_score,
                delivery_mode: 'offline',
            },
        ],
    })).post(`/sahodaya-admin/${props.sahodaya.id}/mcq-series/${props.series.id}/levels/bulk`);
}
</script>
