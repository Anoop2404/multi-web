<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="`Results — ${exam.title}`"
        subtitle="Published marks, grade, and rank"
        accent="emerald"
        :nav-items="navItems"
    >
        <div v-if="!exam.results_published" class="card p-3 mb-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm">
            Results aren't published for this exam yet — marks below may still change.
        </div>
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Hall ticket</th>
                        <th class="p-3">Student</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Score</th>
                        <th class="p-3">Percentage</th>
                        <th class="p-3">Grade</th>
                        <th class="p-3">Rank</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="reg in registrations" :key="reg.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ reg.hall_ticket_no ?? '—' }}</td>
                        <td class="p-3 font-medium">{{ reg.student?.name ?? '—' }}</td>
                        <td class="p-3 text-gray-600">{{ reg.school?.name ?? '—' }}</td>
                        <td class="p-3 font-mono">{{ reg.mark?.score ?? '—' }}</td>
                        <td class="p-3 font-mono">{{ reg.mark?.percentage != null ? `${reg.mark.percentage}%` : '—' }}</td>
                        <td class="p-3">{{ reg.mark?.grade ?? '—' }}</td>
                        <td class="p-3 font-bold">{{ reg.mark?.rank ?? '—' }}</td>
                    </tr>
                    <tr v-if="!registrations.length">
                        <td colspan="7" class="p-8 text-center text-gray-400">No marks entered yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';

const props = defineProps({
    sahodaya: Object,
    exam: Object,
    registrations: { type: Array, default: () => [] },
    gradeBands: { type: Array, default: () => [] },
});

const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id));
</script>
