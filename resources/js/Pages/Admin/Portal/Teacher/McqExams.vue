<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Talent Search Exams"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                        📝 Open Talent Search Competitions
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Sahodaya Talent Search exams open for teacher enrolment.</p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-50 text-[#0f3d7a] border border-blue-200">
                    {{ openExams?.length ?? 0 }} Exams
                </span>
            </div>

            <div v-if="openExams?.length" class="grid gap-4 sm:grid-cols-2">
                <div v-for="e in openExams" :key="e.id" class="program-card group flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-[#0f3d7a]/30">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="h-10 w-10 rounded-xl bg-gradient-to-br from-[#041525] to-[#0f3d7a] text-white flex items-center justify-center font-bold text-lg shrink-0">
                                📝
                            </span>
                            <h3 class="font-bold text-slate-900 text-base group-hover:text-[#0f3d7a] transition truncate">{{ e.title }}</h3>
                        </div>

                        <div class="mt-4 space-y-2 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            <p v-if="e.scheduled_at_label" class="flex items-center gap-2 font-medium text-slate-700">
                                ⏰ Schedule: {{ e.scheduled_at_label }}
                            </p>
                            <p v-if="e.venue" class="flex items-center gap-2 text-slate-600">
                                📍 Venue: {{ e.venue }}
                            </p>
                            <p v-if="e.has_fee" class="flex items-center gap-2 font-bold text-slate-800">
                                💳 Fee: ₹{{ e.fee_amount }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <button v-if="e.can_register"
                                type="button"
                                class="btn-primary text-xs w-full justify-center !min-h-0 !py-2.5 shadow-sm"
                                :disabled="registering === e.id"
                                @click="register(e)">
                            {{ registering === e.id ? 'Registering…' : 'Register Now' }}
                        </button>
                        <span v-else-if="e.registered" class="inline-flex items-center justify-center w-full py-2 rounded-xl bg-emerald-50 text-emerald-800 font-bold text-xs border border-emerald-200">
                            ✓ Registered
                        </span>
                        <div v-else-if="e.ineligibility_reason" class="text-xs text-amber-800 bg-amber-50 border border-amber-200/80 rounded-xl px-3 py-2">
                            ⚠️ {{ e.ineligibility_reason }}
                        </div>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="No open Talent Search exams" description="Teacher Talent Search exams open for registration will appear here." icon="📝" />
        </section>

        <!-- My Registrations -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    📋 My Exam Registrations & Hall Tickets
                </h2>
                <span class="text-xs font-semibold text-slate-500">Total: {{ registrations?.length ?? 0 }}</span>
            </div>

            <div v-if="registrations?.length" class="space-y-3">
                <div v-for="r in registrations" :key="r.id" class="p-4 rounded-2xl border border-slate-200/90 bg-white shadow-sm space-y-2">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-bold text-slate-900 text-base">{{ r.exam?.title }}</h3>
                        <span class="track-status-pill font-bold" :class="pillClass(r.approval_status)">
                            {{ r.approval_status_label || r.approval_status }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600 pt-2 border-t border-slate-100">
                        <span class="capitalize font-semibold text-slate-700">Status: {{ r.status }}</span>
                        <span v-if="r.show_hall_ticket" class="bg-amber-50 text-amber-900 font-mono font-bold px-2 py-0.5 rounded border border-amber-200">
                            Ticket #{{ r.hall_ticket_no }}
                        </span>
                        <span v-if="r.show_hall_ticket && (r.hall_room || r.seat_no)" class="text-slate-700 font-medium">
                            Hall: {{ r.hall_room || '—' }} {{ r.seat_no ? `· Seat ${r.seat_no}` : '' }}
                        </span>
                        <span v-else-if="r.approval_status === 'approved' && !r.show_hall_ticket" class="text-slate-400 italic">
                            Hall ticket not released yet
                        </span>
                    </div>

                    <div v-if="r.score != null" class="mt-2 text-xs font-bold text-slate-800 bg-slate-50 p-2.5 rounded-xl border border-slate-100 flex items-center justify-between">
                        <span>Score: {{ r.score }} points</span>
                        <span>Grade: {{ r.grade || '—' }} · Rank: #{{ r.rank ?? '—' }}</span>
                    </div>
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

