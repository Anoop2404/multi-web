<template>
    <SchoolAdminLayout :title="`Event Reports`" :school="school" :show-header-title="false">
        <PageHeader :title="`${programLabel} reports`" :eyebrow="programLabel"
                    description="Participation, student-wise, item-wise reports and admit cards per event." />


        <p class="text-sm text-gray-500 mb-4">{{ programLabel }} reports for your school</p>
        <ul class="card-list">
            <li v-for="ev in events" :key="ev.id" class="p-4">
                <p class="font-medium">{{ ev.title }} <span class="text-xs text-gray-400">({{ ev.status }})</span></p>
                <div class="flex flex-wrap gap-2 mt-2 text-xs">
                    <a :href="`${programBase}/reports/${ev.id}/participation`" class="text-indigo-600 font-semibold">Participation</a>
                    <a :href="`${programBase}/reports/${ev.id}/registration-register`" class="text-indigo-600 font-semibold">Reg & fees register</a>
                    <a :href="`${programBase}/reports/${ev.id}/fee-summary`" class="text-indigo-600 font-semibold">Fee summary</a>
                    <a :href="`${programBase}/reports/${ev.id}/id-cards`" class="text-indigo-600 font-semibold">ID cards</a>
                    <a :href="`${programBase}/reports/${ev.id}/student-wise`" class="text-indigo-600 font-semibold">Student-wise</a>
                    <a v-if="programSlug === 'teacher-fest'" :href="`${programBase}/reports/${ev.id}/teacher-wise`" class="text-indigo-600 font-semibold">Teacher-wise</a>
                    <a :href="`${programBase}/reports/${ev.id}/item-wise`" class="text-indigo-600 font-semibold">Item-wise</a>
                    <a :href="`${programBase}/reports/${ev.id}/discipline-participation`" class="text-indigo-600 font-semibold">Discipline</a>
                    <a :href="`${programBase}/reports/${ev.id}/schedule-clashes`" class="text-indigo-600 font-semibold">Clashes</a>
                    <a :href="`${programBase}/reports/${ev.id}/mark-entry-status`" class="text-indigo-600 font-semibold">Mark status</a>
                    <a :href="`${programBase}/reports/${ev.id}/results-summary`" class="text-indigo-600 font-semibold">Results</a>
                    <a :href="`${programBase}/reports/${ev.id}/group-roster`" target="_blank" class="text-indigo-600 font-semibold">Group roster PDF</a>
                    <a :href="`${programBase}/reports/${ev.id}/attendance-sheet`" target="_blank" class="text-indigo-600 font-semibold">Attendance PDF</a>
                    <a :href="`${programBase}/reports/${ev.id}/student-wise/export`" class="text-indigo-600 font-semibold">Student CSV</a>
                    <a v-if="programSlug === 'teacher-fest'" :href="`${programBase}/reports/${ev.id}/teacher-wise/export`" class="text-indigo-600 font-semibold">Teacher CSV</a>
                    <a :href="`${programBase}/reports/${ev.id}/item-wise/export`" class="text-indigo-600 font-semibold">Item CSV</a>
                    <a :href="`${programBase}/reports/${ev.id}/admit-cards`" target="_blank" class="text-indigo-600 font-semibold">Admit cards PDF</a>
                </div>
            </li>
            <li v-if="!events.length" class="p-6 text-center text-gray-400">No events yet.</li>
        </ul>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({ school: Object, program: [String, Object], programMeta: { type: Object, default: null }, events: Array });
const { programSlug, programLabel, programBase } = useSchoolProgramContext(props);
</script>
