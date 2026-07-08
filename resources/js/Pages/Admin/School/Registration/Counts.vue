<template>
    <SchoolAdminLayout title="Student Counts" :school="school" :show-header-title="false">
        <PageHeader title="Student counts for membership" eyebrow="Membership"
                    description="Enter headcount by class category. Totals should match male + female counts." />

        <div class="max-w-3xl space-y-5">
            <MembershipWorkflowNav :school="school"
                                   :profile="profile"
                                   :registration="registration"
                                   current="counts" />

            <div class="flex flex-wrap items-center gap-3">
                <TrackStatusPill :status="submission.counts_status" />
            </div>

            <div v-if="countMismatch" class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Count mismatch</p>
                <p class="mt-1">
                    Your database has {{ dbStudentCount }} active students, but submitted totals differ by more than 10%.
                    Review counts before submitting.
                </p>
            </div>

            <form @submit.prevent="save" class="card card--flush overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="p-3">Category</th>
                                <th class="p-3 w-28">Male</th>
                                <th class="p-3 w-28">Female</th>
                                <th class="p-3 w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cat in categories" :key="cat.id" class="border-t">
                                <td class="p-3 font-medium">{{ cat.label }}</td>
                                <td class="p-3">
                                    <input v-model.number="rows[cat.id].male_count" type="number" min="0" class="field" aria-label="Male count">
                                </td>
                                <td class="p-3">
                                    <input v-model.number="rows[cat.id].female_count" type="number" min="0" class="field" aria-label="Female count">
                                </td>
                                <td class="p-3">
                                    <input v-model.number="rows[cat.id].total_count" type="number" min="0" class="field" aria-label="Total count">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end p-4 border-t border-slate-100">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save counts</button>
                </div>
            </form>

            <div v-if="categories.some(c => mismatch(c.id))" class="notice-banner notice-banner--warning text-sm">
                Some rows have totals that do not match male + female. Fix before submitting.
            </div>

            <button v-if="canSubmit"
                    type="button"
                    class="btn-primary"
                    @click="submit">
                Submit counts for Sahodaya review
            </button>
            <p v-else-if="submission.counts_status === 'submitted'" class="text-sm text-amber-700">
                Awaiting Sahodaya approval…
            </p>
            <p v-else-if="submission.counts_status === 'approved'" class="text-sm text-emerald-700">
                Student counts approved.
            </p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import MembershipWorkflowNav from '@/Components/school/MembershipWorkflowNav.vue';
import TrackStatusPill from '@/Components/ui/TrackStatusPill.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useScrollToFirstError } from '@/composables/useScrollToFirstError.js';

const props = defineProps({
    school: Object,
    registration: Object,
    submission: Object,
    profile: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    dbStudentCount: { type: Number, default: 0 },
    countMismatch: { type: Boolean, default: false },
});

const { scrollToFirstError } = useScrollToFirstError();

const rows = reactive(Object.fromEntries(props.categories.map(c => [c.id, {
    class_category_id: c.id,
    male_count: props.counts[c.id]?.male_count ?? 0,
    female_count: props.counts[c.id]?.female_count ?? 0,
    total_count: props.counts[c.id]?.total_count ?? 0,
}])));

const form = useForm({ counts: [] });

const canSubmit = computed(() =>
    ['pending', 'rejected'].includes(props.submission?.counts_status),
);

function mismatch(id) {
    const r = rows[id];
    return r.total_count !== r.male_count + r.female_count;
}

function save() {
    form.counts = Object.values(rows);
    form.post(`/school-admin/${props.school.id}/registration/counts`, {
        preserveScroll: true,
        onError: () => scrollToFirstError(form.errors),
    });
}

function submit() {
    if (!confirm('Submit student counts for Sahodaya review?')) return;
    router.post(`/school-admin/${props.school.id}/registration/submit-track`, { track: 'counts' });
}
</script>
