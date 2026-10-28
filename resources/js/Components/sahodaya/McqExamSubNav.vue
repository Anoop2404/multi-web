<template>
    <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 mb-4">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    examId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    deliveryMode: { type: String, default: 'offline' },
    resultsPublished: { type: Boolean, default: false },
    // true/false when the page knows whether the exam runs on the online exam engine; null = unknown
    // (both "Live session" and "Live monitor" are shown; the monitor explains when it doesn't apply).
    usesEngine: { type: [Boolean, null], default: null },
    /** When true, hide Talent Search fee/hall-ticket ceremony tabs. */
    isModelExam: { type: Boolean, default: false },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.examId}`);

const questionBankLabel = computed(() =>
    props.deliveryMode === 'online'
        ? 'Paper'
        : 'Paper (online only)',
);

const tabs = computed(() => {
    const list = [
        { key: 'overview', label: 'Overview', href: base.value },
    ];

    if (!props.isModelExam) {
        list.push(
            { key: 'payments', label: 'Payments', href: `${base.value}/payments` },
            { key: 'ledger', label: 'Ledger', href: `${base.value}/ledger` },
        );
    }

    list.push({ key: 'question-banks', label: questionBankLabel.value, href: `${base.value}/question-banks` });

    if (!props.isModelExam) {
        list.push(
            { key: 'centre-codes', label: 'Centre codes', href: `${base.value}/centre-codes` },
            { key: 'hall-tickets', label: 'Hall tickets', href: `${base.value}/hall-tickets` },
        );
    }

    list.push(
        { key: 'attendance', label: 'Attendance', href: `${base.value}/attendance` },
        { key: 'attendance-corrections', label: 'Corrections', href: `${base.value}/attendance-corrections` },
        { key: 'results', label: 'Results & marks', href: `${base.value}/results` },
        { key: 'reports', label: 'Reports', href: `${base.value}/reports` },
    );

    if (props.deliveryMode === 'online') {
        if (props.usesEngine !== true) {
            list.push({ key: 'session', label: 'Live session', href: `${base.value}/session` });
        }
        if (props.usesEngine !== false) {
            list.push({ key: 'live', label: 'Live monitor', href: `${base.value}/live` });
        }
    }

    if (props.resultsPublished) {
        list.push({ key: 'leaderboard', label: 'Leaderboard', href: `${base.value}/leaderboard` });
    }

    list.push(
        { key: 'staff', label: 'Exam staff', href: `${base.value}/staff` },
        { key: 'activity', label: 'Activity', href: `${base.value}/activity` },
    );

    return list;
});
</script>
