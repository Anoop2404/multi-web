<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Talent Search exams"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <section v-if="openExams?.length" class="card mb-4">
            <h2 class="font-semibold text-sm mb-1 text-slate-900">Open exams</h2>
            <p class="text-xs text-slate-500 mb-3">Register for Sahodaya Talent Search exams open to teachers.</p>
            <div v-for="e in openExams" :key="e.id" class="border-t first:border-0 pt-4 first:pt-0 mb-4 last:mb-0">
                <p class="font-medium text-sm text-slate-900">{{ e.title }}</p>
                <p class="text-xs text-slate-500 mt-1">
                    <span v-if="e.scheduled_at_label">{{ e.scheduled_at_label }}</span>
                    <span v-if="e.venue"> · {{ e.venue }}</span>
                    <span v-if="e.has_fee"> · Fee ₹{{ e.fee_amount }}</span>
                </p>
                <button v-if="e.can_register"
                        type="button"
                        class="btn-primary text-xs mt-2 !min-h-0 !py-1.5 !px-3"
                        :disabled="registering === e.id"
                        @click="register(e)">
                    {{ registering === e.id ? 'Registering…' : 'Register' }}
                </button>
                <p v-else-if="e.registered" class="text-xs font-semibold text-emerald-700 mt-2">Registered</p>
                <p v-else-if="e.ineligibility_reason" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded px-2 py-1.5 mt-2">
                    {{ e.ineligibility_reason }}
                </p>
            </div>
        </section>

        <section v-else class="card mb-4">
            <h2 class="font-semibold text-sm mb-1 text-slate-900">Open exams</h2>
            <p class="text-sm text-slate-400 py-3">No teacher Talent Search exams are open right now.</p>
        </section>

        <section class="card">
            <h2 class="font-semibold text-sm mb-2 text-slate-900">My registrations</h2>
            <div v-for="r in registrations" :key="r.id" class="border-t first:border-0 pt-4 first:pt-0 mb-4 last:mb-0">
                <p class="font-medium text-sm text-slate-900">{{ r.exam?.title }}</p>
                <p class="text-xs text-slate-500 capitalize">
                    {{ r.status }} · {{ r.approval_status_label || r.approval_status }}
                    <span v-if="r.hall_ticket_no"> · Ticket {{ r.hall_ticket_no }}</span>
                    <span v-if="r.hall_room || r.seat_no"> · {{ r.hall_room || '—' }}{{ r.seat_no ? ` · Seat ${r.seat_no}` : '' }}</span>
                </p>
                <p v-if="r.score != null" class="text-xs text-slate-600 mt-1">
                    Score {{ r.score }} · Grade {{ r.grade || '—' }} · Rank {{ r.rank ?? '—' }}
                </p>
            </div>
            <p v-if="!registrations?.length" class="text-sm text-slate-400 py-3">No registrations yet.</p>
        </section>
    </PortalLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    school: Object,
    teacher: Object,
    openExams: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
});

const registering = ref(null);
const base = computed(() => `/portal/teacher/${props.school.id}`);
const navItems = computed(() => [
    { href: base.value, label: 'Home' },
    { href: `${base.value}/exams`, label: 'Talent Search' },
    { href: `${base.value}/question-banks`, label: 'Question banks' },
    { href: `${base.value}/training`, label: 'Training' },
]);

function register(exam) {
    registering.value = exam.id;
    router.post(`${base.value}/exams/${exam.id}/register`, {}, {
        preserveScroll: true,
        onFinish: () => { registering.value = null; },
    });
}
</script>
