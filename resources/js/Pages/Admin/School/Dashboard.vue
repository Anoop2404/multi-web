<template>
    <SchoolAdminLayout title="Dashboard" :school="school" :show-header-title="false">
        <PageHeader
            title="School portal"
            eyebrow="Dashboard"
            description="Manage students and complete annual Sahodaya membership registration each academic year."
        />

        <div class="max-w-4xl space-y-6">
            <div v-if="membershipComplete" class="notice-banner notice-banner--success">
                <p class="font-semibold">{{ membershipComplete.academicYear }} membership complete</p>
                <p class="mt-1 text-sm opacity-90">
                    Membership No: <span class="font-mono font-bold">{{ membershipComplete.regNo }}</span>
                </p>
            </div>

            <div class="card space-y-3">
                <h2 class="section-title text-base">Welcome</h2>
                <p class="text-sm leading-relaxed text-slate-600">
                    Use this panel to manage your school's student records and complete
                    <strong>annual Sahodaya membership registration</strong> each academic year.
                </p>
                <ul class="list-inside list-disc space-y-1.5 text-sm text-slate-600">
                    <li><strong>Students</strong> — class-wise records using classes set by your Sahodaya</li>
                    <li><strong>Annual registration</strong> — submit counts/teachers and membership payment to Sahodaya</li>
                </ul>
            </div>

            <div class="card">
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
                            <p v-if="setup.hasSchoolCode" class="mt-1 font-mono text-xs text-emerald-700">{{ setup.schoolCode }}</p>
                            <Link v-else :href="`/school-admin/${school.id}/setup/code`" class="link-brand mt-2 inline-block text-xs">
                                Set school code →
                            </Link>
                        </div>
                    </li>

                    <li class="step-item" :class="!setup.hasSchoolCode && 'opacity-50'">
                        <span class="step-badge" :class="setup.studentCount > 0 ? 'step-badge--done' : setup.hasSchoolCode ? 'step-badge--active' : 'step-badge--pending'">
                            {{ setup.studentCount > 0 ? '✓' : '2' }}
                        </span>
                        <div>
                            <p class="font-semibold text-slate-900">Register students</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Classes (1–12, etc.) are provided by your Sahodaya — pick the class when registering.
                                Or <Link :href="`/school-admin/${school.id}/students?import=1`" class="link-brand">import from CSV</Link>.
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
                <div v-for="stat in stats" :key="stat.label" class="stat-tile">
                    <p class="stat-tile-label">{{ stat.label }}</p>
                    <p class="stat-tile-value">{{ stat.value }}</p>
                </div>
            </div>

            <div v-if="programSummaries?.length" class="card">
                <h3 class="section-title text-base mb-4">Fest programs</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <Link v-for="p in programSummaries" :key="p.slug" :href="p.hub_url"
                          class="rounded-xl border border-slate-200 p-4 hover:border-[#0f3d7a]/40 hover:bg-slate-50/80 transition">
                        <p class="font-semibold text-slate-900">{{ p.label }}</p>
                        <p class="text-xs text-slate-500 mt-2">
                            {{ p.open_events }} open · {{ p.registrations }} registrations
                            <span v-if="p.fees_pending" class="text-amber-700 font-semibold"> · {{ p.fees_pending }} fee(s) pending</span>
                        </p>
                    </Link>
                </div>
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
import { Link } from '@inertiajs/vue3';

defineProps({
    school: Object,
    stats:  { type: Array, default: () => [] },
    programSummaries: { type: Array, default: () => [] },
    dashboardExtras: { type: Object, default: () => ({}) },
    setup:  { type: Object, required: true },
    membershipComplete: { type: Object, default: null },
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
</script>
