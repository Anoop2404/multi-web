<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Talent Search exams"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <section class="card mb-5">
            <h2 class="section-title text-base mb-1">Open exams</h2>
            <p class="text-xs text-slate-500 mb-4">Register for Sahodaya Talent Search exams open to teachers.</p>

            <div v-if="openExams?.length" class="grid gap-3 sm:grid-cols-2">
                <div v-for="e in openExams" :key="e.id" class="program-card">
                    <div class="flex items-center gap-3">
                        <span class="program-card-icon">📝</span>
                        <p class="font-semibold text-slate-900">{{ e.title }}</p>
                    </div>
                    <p class="text-xs text-slate-500">
                        <span v-if="e.scheduled_at_label">{{ e.scheduled_at_label }}</span>
                        <span v-if="e.venue"> · {{ e.venue }}</span>
                        <span v-if="e.has_fee"> · Fee ₹{{ e.fee_amount }}</span>
                    </p>
                    <button v-if="e.can_register"
                            type="button"
                            class="btn-primary text-xs mt-1 !min-h-0 !py-1.5 !px-3 w-fit"
                            :disabled="registering === e.id"
                            @click="register(e)">
                        {{ registering === e.id ? 'Registering…' : 'Register' }}
                    </button>
                    <p v-else-if="e.registered" class="status-pill status-pill--completed w-fit">Registered</p>
                    <p v-else-if="e.ineligibility_reason" class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-2.5 py-1.5">
                        {{ e.ineligibility_reason }}
                    </p>
                </div>
            </div>
            <EmptyState v-else title="No open Talent Search exams" description="Teacher Talent Search exams open for registration will appear here." icon="📝" />
        </section>

        <section class="card">
            <h2 class="section-title text-base mb-3">My registrations</h2>
            <div v-if="registrations?.length" class="text-sm divide-y divide-slate-100">
                <div v-for="r in registrations" :key="r.id" class="py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="font-medium text-slate-900">{{ r.exam?.title }}</p>
                        <span class="track-status-pill" :class="pillClass(r.approval_status)">{{ r.approval_status_label || r.approval_status }}</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5 capitalize">
                        {{ r.status }}
                        <span v-if="r.hall_ticket_no"> · Ticket {{ r.hall_ticket_no }}</span>
                        <span v-if="r.hall_room || r.seat_no"> · {{ r.hall_room || '—' }}{{ r.seat_no ? ` · Seat ${r.seat_no}` : '' }}</span>
                    </p>
                    <p v-if="r.score != null" class="text-xs text-slate-600 mt-1">
                        Score {{ r.score }} · Grade {{ r.grade || '—' }} · Rank {{ r.rank ?? '—' }}
                    </p>
                </div>
            </div>
            <EmptyState v-else title="No registrations yet" description="Register for an open exam above to see it here." icon="📝" />
        </section>
    </PortalLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    openExams: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
});

const registering = ref(null);
const base = computed(() => `/portal/teacher/${props.school.id}`);
const navItems = computed(() => teacherPortalNavItems(props.school.id));

function pillClass(status) {
    const key = String(status ?? '').toLowerCase();
    if (['approved', 'confirmed'].includes(key)) return 'track-status-pill--approved';
    if (['rejected', 'declined'].includes(key)) return 'track-status-pill--rejected';
    if (['submitted', 'pending', 'review'].includes(key)) return 'track-status-pill--submitted';
    return 'track-status-pill--pending';
}

function register(exam) {
    registering.value = exam.id;
    router.post(`${base.value}/exams/${exam.id}/register`, {}, {
        preserveScroll: true,
        onFinish: () => { registering.value = null; },
    });
}
</script>
