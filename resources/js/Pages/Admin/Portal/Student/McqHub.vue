<template>
    <PortalLayout
        role-label="Student Portal"
        title="Talent Search Exams"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Your Talent Search registrations</h2>
            <ul class="text-sm divide-y">
                <li v-for="r in mcqExams" :key="r.registration_route_id ?? r.id" class="py-3 flex justify-between items-center gap-2 flex-wrap">
                    <div>
                        <span class="font-medium">{{ r.exam?.title ?? r.title }}</span>
                        <span v-if="r.lifecycle_status?.label" class="text-xs ml-2 px-2 py-0.5 rounded-full"
                              :class="lifecycleClass(r.lifecycle_status)">{{ r.lifecycle_status.label }}</span>
                        <span v-else class="text-xs text-gray-400 ml-1">({{ r.status }})</span>
                        <span v-if="r.delivery_mode === 'offline'" class="text-xs text-slate-500 ml-1">· offline</span>
                        <span v-else-if="r.delivery_mode === 'online'" class="text-xs text-indigo-600 ml-1">· online</span>
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
                        <a v-if="r.certificate_url"
                           :href="r.certificate_url"
                           target="_blank"
                           class="text-xs font-semibold text-emerald-700">Certificate ↗</a>
                    </div>
                </li>
                <li v-if="!mcqExams?.length" class="py-4 text-center text-gray-400">
                    <p>No Talent Search registrations yet.</p>
                    <a :href="`/portal/student/${school.id}`" class="text-xs text-indigo-600 font-semibold mt-2 inline-block">← Back to dashboard</a>
                </li>
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

function lifecycleClass(status) {
    const tone = status?.tone ?? 'neutral';
    if (tone === 'success') return 'bg-emerald-50 text-emerald-800';
    if (tone === 'warning') return 'bg-amber-50 text-amber-800';
    if (tone === 'danger') return 'bg-red-50 text-red-800';
    if (tone === 'info') return 'bg-sky-50 text-sky-800';
    return 'bg-slate-100 text-slate-700';
}
</script>
