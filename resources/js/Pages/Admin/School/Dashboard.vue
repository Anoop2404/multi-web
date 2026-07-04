<template>
    <SchoolAdminLayout title="Dashboard" :school="school" :show-header-title="false">
        <PageHeader
            title="School portal"
            eyebrow="Dashboard"
            description="Manage students and complete annual Sahodaya membership registration each academic year."
        />

        <div class="max-w-4xl space-y-6">
            <div v-if="leadershipContacts && !leadershipContacts.complete" class="notice-banner notice-banner--warning">
                <p class="font-semibold">Action required — leadership contacts pending</p>
                <p class="mt-1 text-sm">
                    {{ leadershipContacts.summary }}.
                    <Link :href="`/school-admin/${school.id}/registration/profile`" class="font-semibold underline ml-1">
                        Complete registration profile →
                    </Link>
                </p>
            </div>

            <div v-if="registrationClosingSoon" class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Annual registration closes soon</p>
                <p class="mt-1">
                    Membership registration closes on {{ formatWindowDate(windowDisplayEnd(registrationWindow)) }}
                    ({{ registrationClosingDays }} day{{ registrationClosingDays === 1 ? '' : 's' }} left).
                    <Link :href="`/school-admin/${school.id}/registration`" class="font-semibold underline ml-1">Continue registration →</Link>
                </p>
            </div>

            <div v-if="membershipComplete" class="notice-banner notice-banner--success">
                <p class="font-semibold">{{ membershipComplete.academicYear }} membership complete</p>
                <p class="mt-1 text-sm opacity-90">
                    Membership No: <span class="font-mono font-bold">{{ membershipComplete.regNo }}</span>
                </p>
            </div>

            <div v-if="showSetupWizard && !setupComplete" class="card space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="section-title text-base">Welcome</h2>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="dismissWizard">Dismiss</button>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Use this panel to manage your school's student records and complete
                    <strong>annual Sahodaya membership registration</strong> each academic year.
                </p>
                <ul class="list-inside list-disc space-y-1.5 text-sm text-slate-600">
                    <li><strong>Students</strong> — class-wise records using classes set by your Sahodaya</li>
                    <li><strong>Annual registration</strong> — submit counts/teachers and membership payment to Sahodaya</li>
                </ul>
            </div>

            <div v-if="showSetupWizard && !setupComplete" class="card">
                <h3 class="section-title text-base mb-4">Get started</h3>
                <ol class="step-list">
                    <li class="step-item" :class="!setup.hasSchoolCode && 'opacity-100'">
                        <span class="step-badge" :class="setup.hasSchoolCode ? 'step-badge--done' : 'step-badge--active'">
                            {{ setup.hasSchoolCode ? '✓' : '1' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">Set your school code</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                A short code unique within your Sahodaya — used in student registration numbers
                                <span v-if="setup.regNoExample" class="font-mono">(e.g. {{ setup.regNoExample }})</span>.
                            </p>
                            <p v-if="setup.codeLocked" class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                                Your school code is locked and cannot be changed — all registration numbers depend on it.
                            </p>
                            <p v-if="setup.hasSchoolCode" class="mt-1 font-mono text-xs text-emerald-700">{{ setup.schoolCode }}</p>
                            <Link v-else :href="`/school-admin/${school.id}/setup/code`" class="link-brand mt-2 inline-block text-xs">
                                Set school code →
                            </Link>
                        </div>
                    </li>

                    <li class="step-item" :class="!setup.hasSchoolCode && 'opacity-50'">
                        <span class="step-badge" :class="stepTwoDone ? 'step-badge--done' : setup.hasSchoolCode ? 'step-badge--active' : 'step-badge--pending'">
                            {{ stepTwoDone ? '✓' : '2' }}
                        </span>
                        <div>
                            <p class="font-semibold text-slate-900">
                                {{ setup.requiresStudents ? 'Register students' : 'Student data (optional)' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <template v-if="setup.requiresStudents">
                                    Classes are provided by your Sahodaya — pick the class when registering.
                                    Or <Link :href="`/school-admin/${school.id}/students?import=1`" class="link-brand">import from CSV</Link>.
                                </template>
                                <template v-else>
                                    Your Sahodaya uses teacher/counts-only registration — student records are optional unless you need them for fest/MCQ.
                                </template>
                            </p>
                            <Link v-if="setup.hasSchoolCode" :href="`/school-admin/${school.id}/students?register=1`"
                                  class="link-brand mt-2 inline-block text-xs">
                                {{ setup.studentCount > 0 ? `View students (${setup.studentCount})` : 'Register first student' }} →
                            </Link>
                        </div>
                    </li>

                    <li class="step-item" :class="!setup.hasSchoolCode && 'opacity-50'">
                        <span class="step-badge" :class="setup.hasRegistration ? 'step-badge--done' : setup.hasSchoolCode ? 'step-badge--active' : 'step-badge--pending'">
                            {{ setup.hasRegistration ? '✓' : '3' }}
                        </span>
                        <div>
                            <p class="font-semibold text-slate-900">Annual membership — {{ setup.academicYear }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">Submit data and payment proof for Sahodaya verification.</p>
                            <Link v-if="setup.hasSchoolCode" :href="`/school-admin/${school.id}/registration`"
                                  class="link-brand mt-2 inline-block text-xs">
                                {{ setup.hasRegistration ? 'Continue registration' : 'Begin annual registration' }} →
                            </Link>
                        </div>
                    </li>
                </ol>
            </div>

            <div v-if="setup.studentCount > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Link v-for="stat in linkedStats" :key="stat.label" :href="stat.href"
                      class="stat-tile hover:border-[#0f3d7a]/40 transition block">
                    <p class="stat-tile-label">{{ stat.label }}</p>
                    <p class="stat-tile-value">{{ stat.value }}</p>
                </Link>
            </div>

            <div v-if="openProgramSummaries.length" class="card">
                <h3 class="section-title text-base mb-4">Fest programs</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <Link v-for="p in openProgramSummaries" :key="p.slug" :href="p.hub_url"
                          class="rounded-xl border border-slate-200 p-4 hover:border-[#0f3d7a]/40 hover:bg-slate-50/80 transition">
                        <p class="font-semibold text-slate-900">{{ p.label }}</p>
                        <p class="text-xs text-slate-500 mt-2">
                            {{ p.open_events }} open · {{ p.registrations }} registrations
                            <span v-if="p.fees_pending" class="text-amber-700 font-semibold"> · {{ p.fees_pending }} fee(s) pending</span>
                        </p>
                    </Link>
                </div>
            </div>

            <div v-if="recentActivity?.length" class="card">
                <h3 class="section-title text-base mb-3">Recent activity</h3>
                <ul class="text-sm divide-y">
                    <li v-for="entry in recentActivity" :key="entry.id" class="py-2">
                        <p class="font-medium text-slate-800">{{ entry.description }}</p>
                        <p class="text-xs text-slate-500 mt-0.5 capitalize">
                            {{ entry.log_name?.replace(/_/g, ' ') || entry.action }}
                            <span v-if="entry.created_at"> · {{ formatActivityDate(entry.created_at) }}</span>
                        </p>
                    </li>
                </ul>
            </div>

            <div v-if="dashboardExtras && Object.keys(dashboardExtras).length" class="space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="stat-tile">
                        <p class="stat-tile-label">Teachers</p>
                        <p class="stat-tile-value">{{ dashboardExtras.teacherCount ?? 0 }}</p>
                    </div>
                    <div class="stat-tile">
                        <p class="stat-tile-label">MCQ registrations</p>
                        <p class="stat-tile-value">{{ dashboardExtras.mcqRegistered ?? 0 }}</p>
                    </div>
                    <div class="stat-tile">
                        <p class="stat-tile-label">Training enrolled</p>
                        <p class="stat-tile-value">{{ dashboardExtras.trainingRegistered ?? 0 }}</p>
                    </div>
                    <div class="stat-tile">
                        <p class="stat-tile-label">Fees due</p>
                        <p class="stat-tile-value text-amber-700">₹{{ fmt(dashboardExtras.pendingPayments?.total) }}</p>
                    </div>
                </div>

                <div v-if="dashboardExtras.pendingActions?.length" class="card border-amber-200 bg-amber-50/50">
                    <h3 class="section-title text-base mb-3 text-amber-900">Action required</h3>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <component
                            :is="a.url ? Link : 'div'"
                            v-for="(a, i) in dashboardExtras.pendingActions"
                            :key="i"
                            v-bind="a.url ? { href: a.url } : {}"
                            class="rounded-xl border border-amber-200 bg-white/80 p-3 transition"
                            :class="a.url ? 'hover:border-amber-400 hover:shadow-sm cursor-pointer' : ''"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800/70">{{ actionTypeLabel(a.type) }}</p>
                            <p class="mt-1 text-sm font-medium text-amber-950">{{ a.label }}</p>
                            <p v-if="a.count > 1" class="mt-1 text-xs text-amber-700">{{ a.count }} items</p>
                            <p v-if="a.url" class="mt-2 text-xs font-semibold text-[#0f3d7a]">Open →</p>
                        </component>
                    </div>
                </div>

                <div v-if="dashboardExtras.upcoming?.length" class="card">
                    <h3 class="section-title text-base mb-3">Upcoming deadlines</h3>
                    <ul class="text-sm divide-y">
                        <li v-for="(u, i) in dashboardExtras.upcoming" :key="i" class="py-2 flex justify-between gap-2">
                            <Link v-if="u.url" :href="u.url" class="link-brand truncate">{{ u.title }}</Link>
                            <span v-else class="truncate">{{ u.title }}</span>
                            <span class="text-xs text-slate-500 shrink-0">{{ u.date }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="dashboardExtras.recentResults?.length" class="card">
                    <h3 class="section-title text-base mb-3">Recent results</h3>
                    <ul class="text-sm divide-y">
                        <li v-for="(r, i) in dashboardExtras.recentResults" :key="i" class="py-2 capitalize">
                            {{ r.title }} <span class="text-xs text-slate-500">· {{ r.type?.replace(/_/g, ' ') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    windowClosingDays,
    windowClosingSoon,
    windowDisplayEnd,
} from '@/support/membershipRegistrationWindow.js';

const props = defineProps({
    school: Object,
    stats:  { type: Array, default: () => [] },
    programSummaries: { type: Array, default: () => [] },
    dashboardExtras: { type: Object, default: () => ({}) },
    setup:  { type: Object, required: true },
    membershipComplete: { type: Object, default: null },
    leadershipContacts: { type: Object, default: null },
    recentActivity: { type: Array, default: () => [] },
    registrationWindow: { type: Object, default: null },
    showSetupWizard: { type: Boolean, default: true },
});

const dismissForm = useForm({});

const stepTwoDone = computed(() =>
    props.setup.requiresStudents ? props.setup.studentCount > 0 : true
);

const setupComplete = computed(() =>
    props.setup.hasSchoolCode
    && stepTwoDone.value
    && props.setup.hasRegistration
);

function dismissWizard() {
    dismissForm.post(`/school-admin/${props.school.id}/setup/dismiss-wizard`);
}

const registrationClosingDays = computed(() => windowClosingDays(props.registrationWindow));

const registrationClosingSoon = computed(() =>
    windowClosingSoon(props.registrationWindow) && !props.membershipComplete
);

const openProgramSummaries = computed(() =>
    (props.programSummaries ?? []).filter(p => (p.open_events ?? 0) > 0)
);

const linkedStats = computed(() => {
    const base = `/school-admin/${props.school.id}`;
    return [
        { label: 'Active students', value: props.stats?.[0]?.value ?? 0, href: `${base}/students` },
        { label: 'Teachers', value: props.stats?.[1]?.value ?? 0, href: `${base}/teachers` },
        { label: 'Annual registration', value: props.membershipComplete ? 'Complete ✓' : (props.setup.hasRegistration ? 'In progress' : 'Start'), href: `${base}/registration` },
    ];
});

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function actionTypeLabel(type) {
    const labels = {
        fest_fee: 'Event fee',
        mcq_fee: 'MCQ fee',
        mcq_fee_review: 'MCQ payment',
        membership: 'Membership',
        mcq_register: 'MCQ registration',
    };
    return labels[type] ?? 'Action';
}

function formatActivityDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function formatWindowDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>
