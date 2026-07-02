<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="sahodaya.name"
        subtitle="Attendance, hall supervision — offline MCQ"
        accent="emerald"
        :nav-items="navItems"
    >
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
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, exams: Array, canMark: Boolean });

const navItems = computed(() => [
    { href: `/portal/exam/${props.sahodaya.id}`, label: 'Exams' },
]);

const stats = computed(() => [
    { label: 'Exams', value: props.exams.length },
    { label: 'Mark entry', value: props.canMark ? 'Enabled' : 'View only' },
]);

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
