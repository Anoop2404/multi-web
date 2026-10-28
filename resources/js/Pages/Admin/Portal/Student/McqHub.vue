<template>
    <PortalLayout
        role-label="Student Portal"
        title="Online exams"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <p class="text-sm font-semibold text-slate-900">Before exam day</p>
                <p class="text-xs text-slate-500 mt-0.5">Check browser, storage, and network on this device.</p>
            </div>
            <a :href="`/portal/student/${school.id}/mcq/system-check`" class="btn-secondary text-sm">Practice system check</a>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                :class="filter === tab.id
                    ? 'bg-[#0f3d7a] text-white shadow-sm'
                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                @click="filter = tab.id"
            >{{ tab.label }} ({{ tab.count }})</button>
        </div>

        <section class="space-y-3">
            <StudentMcqExamCard
                v-for="exam in filteredExams"
                :key="exam.registration_route_id ?? exam.id"
                :school-id="school.id"
                :exam="exam"
            />
            <div v-if="!filteredExams.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center">
                <p class="text-sm text-slate-400">No exams in this list.</p>
                <a :href="`/portal/student/${school.id}`" class="text-xs font-bold text-[#0f3d7a] mt-2 inline-block">← Back to home</a>
            </div>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StudentMcqExamCard from '@/Components/portal/StudentMcqExamCard.vue';
import { computed, ref } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    mcqExams: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
const filter = ref('all');

const actionable = computed(() => (props.mcqExams ?? []).filter((e) => e.can_take_online));
const submitted = computed(() => (props.mcqExams ?? []).filter((e) => e.status === 'submitted' || e.show_results));
const other = computed(() => (props.mcqExams ?? []).filter((e) => !e.can_take_online && e.status !== 'submitted' && !e.show_results));

const tabs = computed(() => [
    { id: 'all', label: 'All', count: (props.mcqExams ?? []).length },
    { id: 'actionable', label: 'Take now', count: actionable.value.length },
    { id: 'submitted', label: 'Submitted', count: submitted.value.length },
    { id: 'other', label: 'Upcoming / offline', count: other.value.length },
]);

const filteredExams = computed(() => {
    if (filter.value === 'actionable') return actionable.value;
    if (filter.value === 'submitted') return submitted.value;
    if (filter.value === 'other') return other.value;
    return props.mcqExams ?? [];
});
</script>
