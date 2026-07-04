<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="sahodaya.name"
        subtitle="Attendance, hall supervision — offline MCQ"
        accent="emerald"
        :nav-items="navItems"
    >
        <div v-if="upcomingExams.length" class="card mb-4 border-emerald-200 bg-emerald-50/60">
            <h2 class="font-semibold text-sm text-emerald-900 mb-2">Starting soon</h2>
            <ul class="text-sm space-y-2">
                <li v-for="exam in upcomingExams" :key="exam.id" class="flex flex-wrap justify-between gap-2">
                    <span class="font-medium text-emerald-950">{{ exam.title }}</span>
                    <span class="font-mono text-xs font-semibold text-emerald-800">{{ countdowns[exam.id] || '…' }}</span>
                </li>
            </ul>
        </div>

        <PortalAssignmentsHub
            title="My exam assignments"
            eyebrow="Exam portal"
            description="MCQ exams where you are assigned hall supervision or mark entry."
            empty-message="No assigned exams."
            :stats="stats"
            :assignments="assignmentCards"
        />
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PortalAssignmentsHub from '@/Components/portal/PortalAssignmentsHub.vue';
import { computed, onMounted, onUnmounted, reactive } from 'vue';

const props = defineProps({ sahodaya: Object, exams: Array, canMark: Boolean });

const navItems = computed(() => [
    { href: `/portal/exam/${props.sahodaya.id}`, label: 'Exams' },
]);

const stats = computed(() => [
    { label: 'Exams', value: props.exams.length },
    { label: 'Mark entry', value: props.canMark ? 'Enabled' : 'View only' },
]);

const upcomingExams = computed(() =>
    (props.exams ?? []).filter((exam) => {
        if (!exam.scheduled_at) return false;
        const diff = new Date(exam.scheduled_at).getTime() - Date.now();
        return diff > 0 && diff <= 2 * 60 * 60 * 1000;
    }),
);

const countdowns = reactive({});
let timer = null;

function formatCountdown(ms) {
    if (ms <= 0) return 'Starting now';
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    if (hours > 0) return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    return `${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
}

function tickCountdowns() {
    for (const exam of upcomingExams.value) {
        const diff = new Date(exam.scheduled_at).getTime() - Date.now();
        countdowns[exam.id] = formatCountdown(diff);
    }
}

onMounted(() => {
    tickCountdowns();
    timer = setInterval(tickCountdowns, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const assignmentCards = computed(() =>
    props.exams.map((exam) => {
        const actions = [{
            href: `/portal/exam/${props.sahodaya.id}/exams/${exam.id}/attendance`,
            label: 'Attendance',
        }, {
            href: `/portal/exam/${props.sahodaya.id}/exams/${exam.id}/supervision`,
            label: 'Live supervision',
        }];
        if (props.canMark) {
            actions.push({
                href: `/portal/exam/${props.sahodaya.id}/exams/${exam.id}/marks`,
                label: 'Mark entry',
            });
        }
        return {
            key: exam.id,
            title: exam.title,
            subtitle: [
                exam.status?.replace(/_/g, ' '),
                exam.scheduled_at ? new Date(exam.scheduled_at).toLocaleString() : 'Date TBA',
            ].filter(Boolean).join(' · '),
            actions,
        };
    }),
);
</script>
