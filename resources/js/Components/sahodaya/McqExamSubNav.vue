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
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.examId}`);

const questionBankLabel = computed(() =>
    props.deliveryMode === 'online'
        ? 'Question banks'
        : 'Question banks (optional · online only)',
);

const tabs = computed(() => {
    const list = [
        { key: 'overview', label: 'Overview', href: base.value },
        { key: 'payments', label: 'Payments', href: `${base.value}/payments` },
        { key: 'question-banks', label: questionBankLabel.value, href: `${base.value}/question-banks` },
        { key: 'hall-tickets', label: 'Hall tickets', href: `${base.value}/hall-tickets` },
        { key: 'attendance', label: 'Attendance', href: `${base.value}/attendance` },
        { key: 'results', label: 'Results & marks', href: `${base.value}/results` },
        { key: 'reports', label: 'Reports', href: `${base.value}/reports` },
    ];

    if (props.deliveryMode === 'online') {
        list.push({ key: 'session', label: 'Live session', href: `${base.value}/session` });
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
