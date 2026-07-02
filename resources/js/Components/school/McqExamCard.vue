<template>
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5 mb-2">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                        {{ exam.level_label || 'Level 1' }}
                    </span>
                    <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                        {{ exam.exam_type_label || exam.exam_type }}
                    </span>
                    <span class="status-pill capitalize text-[10px]" :class="statusClass(exam.status)">
                        {{ exam.status_label || exam.status }}
                    </span>
                    <span v-if="(exam.exam_level ?? 1) > 1" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800">
                        {{ exam.eligibility_mode_label }}
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 leading-snug">{{ exam.title }}</h3>
                <p v-if="exam.series_title" class="text-xs text-slate-500 mt-1">Series: {{ exam.series_title }}</p>
                <p v-if="exam.parent_exam_title" class="text-xs text-amber-700 mt-1">Follows {{ exam.parent_exam_title }}</p>
            </div>
            <Link :href="examUrl" class="btn-primary text-sm shrink-0 whitespace-nowrap">Manage exam →</Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4 rounded-xl border border-slate-100 bg-slate-50/80 p-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Schedule</p>
                <p class="text-sm font-medium text-slate-800">{{ exam.scheduled_at_label || 'Not scheduled' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Fee / student</p>
                <p class="text-sm font-semibold" :class="exam.has_fee ? 'text-emerald-700' : 'text-amber-700'">
                    {{ exam.fee_label || 'Not set' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Classes</p>
                <p class="text-sm text-slate-700">{{ exam.eligibility_summary || 'All classes' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">Mode</p>
                <p class="text-sm text-slate-700 capitalize">{{ exam.delivery_mode_label || 'Offline' }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                Registered <strong>{{ exam.my_registration_count ?? 0 }}</strong>
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                Eligible <strong>{{ exam.eligible_count ?? 0 }}</strong>
            </span>
            <span v-if="(exam.pending_registration_count ?? 0) > 0"
                  class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                Pending <strong>{{ exam.pending_registration_count }}</strong>
            </span>
        </div>

        <div v-if="!exam.has_fee && exam.registration_open" class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900">
            Fee amount not set yet — you can still register students. Pay batch fee after Sahodaya configures the rate.
        </div>

        <div v-if="exam.registration_open" class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <Link :href="`${examUrl}/register`" class="btn-secondary text-sm">Register students</Link>
            <Link :href="`${examUrl}/fee`" class="btn-secondary text-sm">Fee & payment</Link>
            <Link v-if="registrations.some(r => r.hall_ticket_no)" :href="`${examUrl}/hall-tickets`" class="btn-secondary text-sm">Hall tickets</Link>
        </div>

        <div v-if="registrations.length" class="border-t border-slate-100 pt-4 mt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Recent registrations</p>
            <div class="space-y-2">
                <div v-for="registration in registrations.slice(0, 5)" :key="registration.id"
                     class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                    <span class="font-medium">{{ registration.student?.name ?? 'Student' }}</span>
                    <span class="text-xs capitalize text-slate-600">{{ registration.approval_status_label || registration.approval_status }}</span>
                </div>
            </div>
            <Link v-if="registrations.length > 5" :href="`${examUrl}/students`" class="link-brand text-xs font-semibold inline-block mt-2">
                View all {{ registrations.length }} students →
            </Link>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    exam: Object,
    school: Object,
    registrations: { type: Array, default: () => [] },
});

const examUrl = computed(() => `/school-admin/${props.school.id}/mcq/${props.exam.id}`);

function statusClass(status) {
    if (status === 'published' || status === 'ongoing') return 'status-pill--published';
    if (status === 'completed') return 'status-pill--success';
    return 'status-pill--draft';
}
</script>
