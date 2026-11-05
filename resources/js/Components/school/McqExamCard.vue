<template>
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5 mb-2">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                        {{ exam.level_label || 'Level 1' }}
                    </span>
                    <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                        {{ exam.exam_type_label || exam.exam_type }}
                    </span>
                    <span class="status-pill capitalize text-[10px]" :class="statusClass(exam.status)">
                        {{ exam.status_label || exam.status }}
                    </span>
                    <span
                        v-if="nextStep"
                        class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"
                        :class="nextStep.chipClass"
                    >
                        Next: {{ nextStep.label }}
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 leading-snug">{{ exam.title }}</h3>
                <p v-if="exam.series_title" class="text-xs text-slate-500 mt-1">Series: {{ exam.series_title }}</p>
                <p v-if="exam.parent_exam_title" class="text-xs text-amber-700 mt-1">Follows {{ exam.parent_exam_title }}</p>
                <p v-if="nextStep?.detail" class="text-xs text-slate-600 mt-2">{{ nextStep.detail }}</p>
            </div>
            <Link :href="nextStep?.href || examUrl" class="btn-primary text-sm shrink-0 whitespace-nowrap">
                {{ nextStep?.action || 'Manage exam' }} →
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4 rounded-xl border border-slate-100 bg-slate-50/80 p-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Schedule</p>
                <p class="text-sm font-medium text-slate-800">{{ exam.scheduled_at_label || 'Not scheduled' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Fee / student</p>
                <p class="text-sm font-semibold" :class="exam.has_fee ? 'text-emerald-700' : 'text-amber-700'">
                    {{ exam.fee_label || 'Not set' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Classes</p>
                <p class="text-sm text-slate-700">{{ exam.eligibility_summary || 'All classes' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Registered</p>
                <p class="text-sm font-semibold text-indigo-800">{{ exam.my_registration_count ?? 0 }}</p>
            </div>
        </div>

        <div v-if="!exam.has_fee && exam.registration_open" class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900">
            Fee amount not set yet — you can still register students. Pay the batch fee after Sahodaya configures the rate.
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    exam: Object,
    school: Object,
    registrations: { type: Array, default: () => [] },
});

const examUrl = computed(() => `/school-admin/${props.school.id}/mcq/${props.exam.id}`);

const nextStep = computed(() => {
    const e = props.exam || {};
    const regs = e.my_registration_count ?? props.registrations.length ?? 0;
    const feeStatus = e.school_fee_status || null;
    const tickets = e.tickets_issued_count ?? props.registrations.filter((r) => r.hall_ticket_no).length;
    const kgEnabled = !!e.school_level_awards_enabled;
    const kgSubmitted = !!e.school_level_awards_submitted;

    if (e.results_published) {
        return {
            label: 'Results',
            action: 'View results',
            detail: 'Results are published for your school.',
            href: `${examUrl.value}/results`,
            chipClass: 'bg-emerald-50 text-emerald-800',
        };
    }

    if (regs === 0 && e.registration_open) {
        return {
            label: 'Register',
            action: 'Register students',
            detail: 'Add eligible students for this exam.',
            href: `${examUrl.value}/register`,
            chipClass: 'bg-indigo-50 text-indigo-800',
        };
    }

    if (regs > 0 && feeStatus !== 'approved' && feeStatus !== 'waived') {
        return {
            label: 'Pay fee',
            action: 'Fee & payment',
            detail: feeStatus === 'proof_uploaded'
                ? 'Payment proof is under Sahodaya review.'
                : 'Upload batch fee proof so hall tickets can be issued.',
            href: `${examUrl.value}/fee`,
            chipClass: 'bg-amber-50 text-amber-900',
        };
    }

    if (tickets === 0) {
        return {
            label: 'Hall tickets',
            action: 'Hall tickets',
            detail: 'Waiting for Sahodaya to issue / publish hall tickets.',
            href: `${examUrl.value}/hall-tickets`,
            chipClass: 'bg-slate-100 text-slate-700',
        };
    }

    const presentCount = e.attendance_present_count ?? 0;

    if (tickets > 0 && presentCount === 0) {
        return {
            label: 'Attendance',
            action: 'Attendance',
            detail: kgEnabled
                ? 'Mark LKG/UKG candidates Present before mark entry.'
                : 'Mark candidates present / absent for exam day.',
            href: `${examUrl.value}/attendance`,
            chipClass: 'bg-slate-100 text-slate-700',
        };
    }

    if (kgEnabled && !kgSubmitted) {
        return {
            label: 'Mark entry',
            action: 'Mark entry',
            detail: 'Enter KG scores, resolve ties, then Promote / Submit.',
            href: `${examUrl.value}/mark-entry`,
            chipClass: 'bg-indigo-50 text-indigo-800',
        };
    }

    if (tickets > 0) {
        return {
            label: 'Attendance',
            action: 'Attendance',
            detail: 'Review or update exam-day attendance.',
            href: `${examUrl.value}/attendance`,
            chipClass: 'bg-slate-100 text-slate-700',
        };
    }

    return {
        label: 'Manage',
        action: 'Open exam',
        detail: null,
        href: examUrl.value,
        chipClass: 'bg-slate-100 text-slate-700',
    };
});

function statusClass(status) {
    if (status === 'published' || status === 'ongoing') return 'status-pill--published';
    if (status === 'completed') return 'status-pill--success';
    return 'status-pill--draft';
}
</script>
