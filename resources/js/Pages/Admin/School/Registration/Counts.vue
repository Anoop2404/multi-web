<template>
    <SchoolAdminLayout title="Student Counts" :school="school" :show-header-title="false">
        <PageHeader title="Student counts for membership" eyebrow="Membership"
                    description="Enter male and female headcount by class. Total is calculated automatically." />

        <div class="max-w-3xl space-y-5">
            <MembershipWorkflowNav :school="school"
                                   :profile="profile"
                                   :registration="registration"
                                   current="counts" />

            <div class="flex flex-wrap items-center gap-3">
                <TrackStatusPill :status="submission.counts_status" />
            </div>

            <div v-if="submission.counts_status === 'approved'" class="notice-banner text-sm">
                <p class="font-semibold">Already approved</p>
                <p class="mt-1">
                    If your enrollment has increased since approval, update the counts below and
                    resubmit for Sahodaya review. If the new total crosses into a higher fee slab,
                    you'll only be asked to pay the difference.
                </p>
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
                                <th class="p-3">Class</th>
                                <th class="p-3 w-28">Male</th>
                                <th class="p-3 w-28">Female</th>
                                <th class="p-3 w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cls in classes" :key="cls.id" class="border-t">
                                <td class="p-3 font-medium">{{ cls.name }}</td>
                                <td class="p-3">
                                    <input v-model.number="rows[cls.id].male_count" type="number" min="0" class="field" aria-label="Male count">
                                </td>
                                <td class="p-3">
                                    <input v-model.number="rows[cls.id].female_count" type="number" min="0" class="field" aria-label="Female count">
                                </td>
                                <td class="p-3">
                                    <input :value="rowTotal(cls.id)" type="number" disabled
                                           class="field bg-slate-50 text-slate-600 font-semibold" aria-label="Total count (calculated)">
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 bg-slate-50 font-bold">
                                <td class="p-3">Total</td>
                                <td class="p-3 text-blue-700">{{ grandTotal.male }}</td>
                                <td class="p-3 text-pink-700">{{ grandTotal.female }}</td>
                                <td class="p-3 text-slate-900">{{ grandTotal.total }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="flex justify-end p-4 border-t border-slate-100">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save counts</button>
                </div>
            </form>

            <p v-if="canSubmit && grandTotal.total < 1" class="text-sm text-red-600">
                Total student count cannot be zero — enter counts before submitting for review.
            </p>
            <button v-if="canSubmit"
                    type="button"
                    class="btn-primary"
                    :disabled="grandTotal.total < 1"
                    @click="submit">
                {{ submission.counts_status === 'approved' ? 'Resubmit revised count for review' : 'Submit counts for Sahodaya review' }}
            </button>
            <p v-else-if="submission.counts_status === 'submitted'" class="text-sm text-amber-700">
                Awaiting Sahodaya approval…
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
    classes: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    dbStudentCount: { type: Number, default: 0 },
    countMismatch: { type: Boolean, default: false },
});

const { scrollToFirstError } = useScrollToFirstError();

const rows = reactive(Object.fromEntries(props.classes.map(c => [c.id, {
    school_class_id: c.id,
    male_count: props.counts[c.id]?.male_count ?? 0,
    female_count: props.counts[c.id]?.female_count ?? 0,
    total_count: props.counts[c.id]?.total_count ?? 0,
}])));

const form = useForm({ counts: [] });

const canSubmit = computed(() =>
    ['pending', 'rejected', 'approved'].includes(props.submission?.counts_status),
);

function rowTotal(id) {
    const r = rows[id];
    return (r.male_count || 0) + (r.female_count || 0);
}

const grandTotal = computed(() => {
    return Object.keys(rows).reduce((acc, id) => {
        const r = rows[id];
        acc.male += r.male_count || 0;
        acc.female += r.female_count || 0;
        acc.total += rowTotal(id);
        return acc;
    }, { male: 0, female: 0, total: 0 });
});

function save() {
    form.counts = Object.values(rows).map(r => ({
        ...r,
        total_count: (r.male_count || 0) + (r.female_count || 0),
    }));
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
