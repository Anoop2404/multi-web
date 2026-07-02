<template>
    <div class="card mb-6 !py-4 border-l-4" :class="bannerClass">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Your progress</p>
                <p class="text-sm font-semibold text-slate-900">{{ currentStep.label }}</p>
                <p class="text-xs text-slate-600 mt-1">{{ currentStep.detail }}</p>
            </div>
            <Link v-if="currentStep.href && currentStep.tab !== activeTab" :href="currentStep.href" class="btn-primary text-sm shrink-0">
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
    schoolId: { type: [String, Number], required: true },
    examId: { type: [String, Number], required: true },
    exam: { type: Object, required: true },
    activeTab: { type: String, default: 'register' },
    registrationCount: { type: Number, default: 0 },
    schoolFee: { type: Object, default: null },
    ticketsIssuedCount: { type: Number, default: 0 },
});

const base = computed(() => `/school-admin/${props.schoolId}/mcq/${props.examId}`);

const hasFee = computed(() => {
    if (props.exam.has_fee === true) return true;
    const amount = Number(props.exam.fee_amount);
    return Number.isFinite(amount) && amount > 0 && (props.exam.fee_type ?? 'none') !== 'none';
});
const feeApproved = computed(() => props.schoolFee?.status === 'approved');
const feeProofUploaded = computed(() =>
    ['proof_uploaded', 'approved'].includes(props.schoolFee?.status ?? ''),
);
const hasTickets = computed(() => props.ticketsIssuedCount > 0);
const examOpen = computed(() => props.exam.registration_open !== false && ['published', 'ongoing'].includes(props.exam.status));

const steps = computed(() => {
    const list = [
        {
            key: 'register',
            shortLabel: 'Register',
            tab: 'register',
            label: props.registrationCount > 0
                ? `${props.registrationCount} student(s) registered`
                : 'Register students',
            detail: examOpen.value
                ? (props.registrationCount > 0
                    ? 'Students are on the exam list. Next: pay the batch fee when Sahodaya has set the amount.'
                    : 'Add eligible students for this exam. Payment comes after registration.')
                : 'Registration is closed for this exam.',
            action: 'Register',
            href: `${base.value}/register`,
            done: props.registrationCount > 0,
        },
        {
            key: 'fee',
            shortLabel: 'Pay fee',
            tab: 'fee',
            label: feeApproved.value
                ? 'Fee approved by Sahodaya'
                : (feeProofUploaded.value ? 'Payment under review' : 'Pay batch fee'),
            detail: !hasFee.value
                ? (props.registrationCount > 0
                    ? 'Sahodaya has not set the per-student fee yet — batch total will appear when they do.'
                    : 'Register students first, then upload payment proof here.')
                : (feeApproved.value
                    ? 'Sahodaya verified your batch payment.'
                    : (feeProofUploaded.value
                        ? 'Sahodaya is reviewing your payment proof.'
                        : `Upload proof for ${props.registrationCount} student(s) × exam fee on the Fee tab.`)),
            action: 'Fee & payment',
            href: `${base.value}/fee`,
            done: feeApproved.value,
        },
        {
            key: 'tickets',
            shortLabel: 'Hall tickets',
            tab: 'hall-tickets',
            label: hasTickets.value ? 'Hall tickets ready' : 'Hall tickets',
            detail: hasTickets.value
                ? `${props.ticketsIssuedCount} hall ticket(s) issued — download PDF.`
                : 'Issued after Sahodaya approves your batch fee payment.',
            action: 'Hall tickets',
            href: `${base.value}/hall-tickets`,
            done: hasTickets.value,
        },
    ];

    if (props.exam.results_published) {
        list.push({
            key: 'results',
            shortLabel: 'Results',
            tab: 'results',
            label: 'View results',
            detail: 'Results published by Sahodaya.',
            action: 'Results',
            href: `${base.value}/results`,
            done: true,
        });
    }

    return list;
});

const currentStep = computed(() => steps.value.find((s) => !s.done) ?? steps.value[steps.value.length - 1]);

const bannerClass = computed(() => {
    if (currentStep.value.key === 'results' || feeApproved.value) return 'border-l-emerald-500 bg-emerald-50/50';
    if (currentStep.value.key === 'register' && examOpen.value) return 'border-l-indigo-500 bg-indigo-50/40';
    return 'border-l-indigo-500 bg-indigo-50/40';
});

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
