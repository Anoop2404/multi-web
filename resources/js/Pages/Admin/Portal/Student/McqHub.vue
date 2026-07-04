<template>
    <PortalLayout
        role-label="Student Portal"
        title="MCQ Exams"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Your MCQ registrations</h2>
            <ul class="text-sm divide-y">
                <li v-for="r in mcqExams" :key="r.id" class="py-3 flex justify-between items-center gap-2 flex-wrap">
                    <div>
                        <span class="font-medium">{{ r.exam?.title }}</span>
                        <span class="text-xs text-gray-400 ml-1">({{ r.status }})</span>
                        <span v-if="r.delivery_mode === 'offline'" class="text-xs text-slate-500 ml-1">· offline</span>
                        <p v-if="r.show_results && r.mark" class="text-xs text-indigo-700 mt-0.5">
                            Score: {{ r.mark.score }}
                            <span v-if="r.mark.grade"> · Grade: {{ r.mark.grade }}</span>
                            <span v-if="r.mark.rank"> · Rank: {{ r.mark.rank }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a v-if="r.can_take_online"
                           :href="`/portal/student/${school.id}/mcq/${r.registration_route_id}/exam`"
                           class="text-xs font-semibold text-indigo-600">Take exam →</a>
                        <a v-if="r.show_hall_ticket"
                           :href="`/portal/student/${school.id}/mcq/${r.registration_route_id}/hall-ticket`"
                           target="_blank"
                           class="text-xs font-semibold text-slate-600">Hall ticket ↗</a>
                    </div>
                </li>
                <li v-if="!mcqExams?.length" class="text-gray-400 py-4">No MCQ registrations yet</li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    mcqExams: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
</script>
