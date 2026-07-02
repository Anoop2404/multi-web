<template>
    <div class="card mb-6 !py-4 border-l-4" :class="bannerClass">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Next step</p>
                <p class="text-sm font-semibold text-slate-900">{{ currentStep.label }}</p>
                <p class="text-xs text-slate-600 mt-1">{{ currentStep.detail }}</p>
            </div>
            <Link v-if="currentStep.href" :href="currentStep.href" class="btn-primary text-sm shrink-0">
                {{ currentStep.action }}
            </Link>
        </div>

        <ol class="flex flex-wrap gap-2">
            <li v-for="(step, i) in steps" :key="step.key"
                class="flex items-center gap-1.5 text-xs rounded-full px-2.5 py-1 border"
                :class="stepStateClass(step, i)">
                <span class="font-bold">{{ i + 1 }}</span>
                <span>{{ step.shortLabel }}</span>
            </li>
        </ol>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    examId: { type: [String, Number], required: true },
    exam: { type: Object, required: true },
    pendingPaymentApprovals: { type: Number, default: 0 },
    ticketsIssuedCount: { type: Number, default: 0 },
    registrationCount: { type: Number, default: 0 },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.examId}`);

const isPublished = computed(() => ['published', 'ongoing', 'completed'].includes(props.exam.status));
const hasFee = computed(() => Boolean(props.exam.has_fee) || Number(props.exam.fee_amount) > 0);
const allTicketsIssued = computed(() =>
    props.registrationCount > 0 && props.ticketsIssuedCount >= props.registrationCount,
);

const steps = computed(() => {
    const list = [
        {
            key: 'setup',
            shortLabel: 'Publish & fee',
            label: 'Publish exam and set fee',
            detail: 'Set per-student fee and change status to Published so schools can register.',
            action: 'Save below',
            href: null,
            done: isPublished.value && hasFee.value,
        },
        {
            key: 'payments',
            shortLabel: 'Approve payments',
            label: 'Approve school batch payments',
            detail: props.pendingPaymentApprovals > 0
                ? `${props.pendingPaymentApprovals} school batch payment(s) waiting for approval.`
                : 'Review uploaded fee proofs and approve to confirm registrations.',
            action: 'Review payments',
            href: `${base.value}#school-fees`,
            done: props.registrationCount === 0 || props.pendingPaymentApprovals === 0,
        },
        {
            key: 'tickets',
            shortLabel: 'Hall tickets',
            label: 'Issue hall tickets',
            detail: allTicketsIssued.value
                ? 'All approved students have hall tickets.'
                : 'Generate registration numbers and print admit cards for approved students.',
            action: 'Open hall tickets',
            href: `${base.value}/hall-tickets`,
            done: allTicketsIssued.value,
        },
        {
            key: 'exam-day',
            shortLabel: 'Attendance',
            label: 'Mark attendance on exam day',
            detail: 'Use attendance tab or exam portal for hall supervision.',
            action: 'Attendance',
            href: `${base.value}/attendance`,
            done: props.exam.status === 'completed',
        },
        {
            key: 'results',
            shortLabel: 'Results',
            label: props.exam.results_published ? 'Results published' : 'Enter marks and publish results',
            detail: props.exam.results_published
                ? 'Students and schools can view results.'
                : 'Import or enter marks, compute ranks, then publish.',
            action: 'Results & marks',
            href: `${base.value}/results`,
            done: Boolean(props.exam.results_published),
        },
    ];

    return list;
});

const currentStep = computed(() => steps.value.find((s) => !s.done) ?? steps.value[steps.value.length - 1]);

const bannerClass = computed(() =>
    currentStep.value.done && currentStep.value.key === 'results'
        ? 'border-l-emerald-500 bg-emerald-50/50'
        : 'border-l-indigo-500 bg-indigo-50/40',
);

function stepStateClass(step, index) {
    if (step.done) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }
    if (step.key === currentStep.value.key) {
        return 'border-indigo-300 bg-indigo-50 text-indigo-900 font-semibold';
    }
    const currentIndex = steps.value.findIndex((s) => s.key === currentStep.value.key);
    if (index < currentIndex) {
        return 'border-emerald-200 bg-emerald-50/60 text-emerald-700';
    }
    return 'border-slate-200 bg-slate-50 text-slate-500';
}
</script>
