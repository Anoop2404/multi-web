<template>
    <SchoolAdminLayout title="Dashboard" :school="school" :show-header-title="false">
        <div class="mx-auto max-w-5xl space-y-6">

            <!-- Hero -->
            <div class="dash-hero">
                <p class="dash-hero-eyebrow">School portal</p>
                <h1 class="dash-hero-title">{{ school.name }}</h1>
                <p class="dash-hero-desc">
                    Manage students, complete annual Sahodaya membership, and participate in fest programs.
                </p>
                <div class="dash-hero-badges">
                    <span v-if="school.school_prefix" class="dash-badge dash-badge--gold">
                        Code · {{ school.school_prefix }}
                    </span>
                    <span v-if="setup.academicYear" class="dash-badge">
                        {{ setup.academicYear }}
                    </span>
                    <span v-if="membershipComplete" class="dash-badge dash-badge--success">
                        Membership complete
                    </span>
                    <span v-else-if="setup.hasRegistration" class="dash-badge">
                        Registration in progress
                    </span>
                </div>
            </div>

            <!-- Live Events & Fests Spotlight Banner -->
            <div v-if="openProgramSummaries.length" 
                 class="p-4 rounded-2xl bg-gradient-to-r from-indigo-950 via-slate-900 to-blue-950 text-white shadow-lg border border-indigo-700/40 flex flex-wrap items-center justify-between gap-4 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-400/20 border border-amber-400/30 flex items-center justify-center text-xl shrink-0">
                        🏆
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2.5 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Live Registrations Open
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-white mt-1">
                            {{ openProgramSummaries.length }} fest program{{ openProgramSummaries.length === 1 ? '' : 's' }} active for your school
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Link v-for="p in openProgramSummaries" :key="p.slug" :href="p.hub_url" 
                          class="px-3.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-xs font-semibold text-white transition-all transform hover:-translate-y-0.5 flex items-center gap-1.5 shadow-sm">
                        <span>{{ programIcon(p.slug) }} {{ p.label }}</span>
                        <span class="text-amber-300">→</span>
                    </Link>
                </div>
            </div>

            <!-- Alerts -->
            <div v-if="alertItems.length" class="space-y-3">
                <div v-for="(alert, i) in alertItems" :key="i"
                     class="notice-banner shadow-sm transition-all hover:shadow"
                     :class="alert.tone === 'success' ? 'notice-banner--success' : 'notice-banner--warning'">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ alert.title }}</p>
                            <p class="mt-1 text-sm">
                                {{ alert.body }}
                                <Link v-if="alert.href" :href="alert.href" class="ml-1 font-semibold underline">
                                    {{ alert.linkLabel }} →
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <QuickActionCard
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href"
                    :label="action.label"
                    :description="action.description"
                    :icon="action.icon"
                />
            </div>

            <!-- Setup wizard -->
            <div v-if="showSetupWizard && !setupComplete" class="card">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="section-title text-base">Get started</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Complete these steps to activate your school portal.
                        </p>
                    </div>
                    <button type="button" class="shrink-0 text-xs text-slate-500 hover:text-slate-700" @click="dismissWizard">
                        Dismiss
                    </button>
                </div>

                <div class="setup-progress" role="progressbar" :aria-valuenow="setupProgress" aria-valuemin="0" aria-valuemax="100">
                    <div class="setup-progress-bar" :style="{ width: `${setupProgress}%` }" />
                </div>
                <p class="mb-5 text-xs font-medium text-slate-500">{{ setupProgress }}% complete · {{ setupStepsDone }}/3 steps</p>

                <div class="setup-step-grid">
                    <div class="setup-step-card"
                         :class="setup.hasSchoolCode ? 'setup-step-card--done' : 'setup-step-card--active'">
                        <span class="step-badge mb-3 inline-flex"
                              :class="setup.hasSchoolCode ? 'step-badge--done' : 'step-badge--active'">
                            {{ setup.hasSchoolCode ? '✓' : '1' }}
                        </span>
                        <p class="font-semibold text-slate-900">School code</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Unique prefix for student registration numbers
                            <span v-if="setup.regNoExample" class="font-mono">(e.g. {{ setup.regNoExample }})</span>.
                        </p>
                        <p v-if="setup.codeLocked" class="mt-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Code is locked — registration numbers depend on it.
                        </p>
                        <p v-if="setup.hasSchoolCode" class="mt-2 font-mono text-sm font-bold text-emerald-700">{{ setup.schoolCode }}</p>
                        <Link v-else :href="`/school-admin/${school.id}/setup/code`" class="link-brand mt-3 inline-block text-xs">
                            Set school code →
                        </Link>
                    </div>

                    <div class="setup-step-card"
                         :class="[
                             stepTwoDone ? 'setup-step-card--done' : setup.hasSchoolCode ? 'setup-step-card--active' : '',
                             !setup.hasSchoolCode && 'opacity-50',
                         ]">
                        <span class="step-badge mb-3 inline-flex"
                              :class="stepTwoDone ? 'step-badge--done' : setup.hasSchoolCode ? 'step-badge--active' : 'step-badge--pending'">
                            {{ stepTwoDone ? '✓' : '2' }}
                        </span>
                        <p class="font-semibold text-slate-900">
                            {{ setup.requiresStudents ? 'Register students' : 'Student data (optional)' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            <template v-if="setup.requiresStudents">
                                Pick class when registering, or
                                <Link :href="`/school-admin/${school.id}/students?bulk=1`" class="link-brand">bulk upload</Link>.
                            </template>
                            <template v-else>
                                Teacher/counts-only registration — student records optional unless needed for fest/Talent Search.
                            </template>
                        </p>
                        <Link v-if="setup.hasSchoolCode" :href="`/school-admin/${school.id}/students?register=1`"
                              class="link-brand mt-3 inline-block text-xs">
                            {{ setup.studentCount > 0 ? `View students (${setup.studentCount})` : 'Register first student' }} →
                        </Link>
                    </div>

                    <div class="setup-step-card"
                         :class="[
                             setup.hasRegistration ? 'setup-step-card--done' : setup.hasSchoolCode ? 'setup-step-card--active' : '',
                             !setup.hasSchoolCode && 'opacity-50',
                         ]">
                        <span class="step-badge mb-3 inline-flex"
                              :class="setup.hasRegistration ? 'step-badge--done' : setup.hasSchoolCode ? 'step-badge--active' : 'step-badge--pending'">
                            {{ setup.hasRegistration ? '✓' : '3' }}
                        </span>
                        <p class="font-semibold text-slate-900">Annual membership</p>
                        <p class="mt-1 text-xs text-slate-500">{{ setup.academicYear }} — submit data and payment proof.</p>
                        <Link v-if="setup.hasSchoolCode" :href="`/school-admin/${school.id}/registration`"
                              class="link-brand mt-3 inline-block text-xs">
                            {{ setup.hasRegistration ? 'Continue registration' : 'Begin registration' }} →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Key stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <DashboardStatCard
                    v-for="stat in linkedStats"
                    :key="stat.label"
                    :label="stat.label"
                    :value="stat.value"
                    :icon="stat.icon"
                    :hint="stat.hint"
                    :href="stat.href"
                    :tone="stat.tone"
                />
            </div>

            <!-- Fest programs -->
            <div v-if="openProgramSummaries.length" class="card">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="section-title text-base">Fest programs</h3>
                    <span class="text-xs font-medium text-slate-500">{{ openProgramSummaries.length }} open</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link v-for="p in openProgramSummaries" :key="p.slug" :href="p.hub_url" class="program-card group">
                        <div class="flex items-center gap-3">
                            <span class="program-card-icon">{{ programIcon(p.slug) }}</span>
                            <p class="font-semibold text-slate-900 group-hover:text-[#0f3d7a]">{{ p.label }}</p>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ p.open_events }} open event{{ p.open_events === 1 ? '' : 's' }}
                            · {{ p.registrations }} registration{{ p.registrations === 1 ? '' : 's' }}
                            <span v-if="p.fees_pending" class="font-semibold text-amber-700">
                                · {{ p.fees_pending }} fee{{ p.fees_pending === 1 ? '' : 's' }} pending
                            </span>
                        </p>
                    </Link>
                </div>
            </div>

            <!-- CPD hours -->
            <div v-if="cpd || boardResultsWidget" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <DashboardStatCard
                    v-if="cpd"
                    label="CPD hours this year"
                    :value="Number(cpd.hours ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 1 })"
                    :hint="cpd.year ? `${cpd.teachers ?? 0} teachers · ${cpd.year}` : `${cpd.teachers ?? 0} teachers`"
                    icon="⏱️"
                    tone="green"
                    :href="`${base}/training`"
                />
                <div v-if="boardResultsWidget" class="card sm:col-span-2">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <h3 class="section-title text-base">Board results</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ boardResultsWidget.academic_year }}</p>
                        </div>
                        <Link :href="boardResultsWidget.href" class="link-brand text-xs">Manage →</Link>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <p class="text-xs text-slate-500">Published</p>
                            <p class="font-semibold text-slate-900">{{ boardResultsWidget.published_count }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 px-3 py-2">
                            <p class="text-xs text-amber-700">In progress</p>
                            <p class="font-semibold text-amber-900">{{ boardResultsWidget.pending_count }}</p>
                        </div>
                    </div>
                    <ul v-if="boardResultsWidget.ranks?.length" class="text-xs text-slate-600 space-y-1 mb-3">
                        <li v-for="(r, i) in boardResultsWidget.ranks" :key="i">
                            Sahodaya rank #{{ r.rank }}
                            <span class="text-slate-400">({{ r.scope?.replace(/_/g, ' ') }})</span>
                        </li>
                    </ul>
                    <ul v-if="boardResultsWidget.toppers?.length" class="text-xs divide-y divide-slate-100">
                        <li v-for="(t, i) in boardResultsWidget.toppers" :key="i" class="flex justify-between py-1.5">
                            <span>{{ t.name }} · Class {{ t.class }}</span>
                            <span class="font-semibold text-indigo-600">{{ t.percentage }}%</span>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-slate-400">No published toppers for this year yet.</p>
                </div>
            </div>

            <!-- Extras: stats, actions, deadlines -->
            <div v-if="dashboardExtras && Object.keys(dashboardExtras).length" class="space-y-6">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <DashboardStatCard label="Student Roster" :value="props.stats?.[0]?.value ?? 0" icon="👨‍🎓" tone="navy" :href="`${base}/students`" />
                    <DashboardStatCard label="Talent Search registrations" :value="dashboardExtras.mcqRegistered ?? 0" icon="📝" tone="indigo" />
                    <DashboardStatCard label="Training enrolled" :value="dashboardExtras.trainingRegistered ?? 0" icon="🎓" tone="green" />
                    <DashboardStatCard label="Fees due" :value="`₹${fmt(dashboardExtras.pendingPayments?.total)}`" icon="💳" tone="amber" />
                </div>

                <div v-if="dashboardExtras.pendingActions?.length" class="card border-amber-200/90 bg-gradient-to-br from-amber-50/90 via-white to-amber-50/40 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="section-title text-base text-amber-950 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                            Action required
                        </h3>
                        <span class="text-xs font-semibold text-amber-800 bg-amber-100 px-2.5 py-0.5 rounded-full">
                            {{ dashboardExtras.pendingActions.length }} pending
                        </span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <component
                            :is="a.url ? Link : 'div'"
                            v-for="(a, i) in dashboardExtras.pendingActions"
                            :key="i"
                            v-bind="a.url ? { href: a.url } : {}"
                            class="rounded-xl border border-amber-200 bg-white p-4 transition-all hover:border-amber-400 hover:shadow-md flex items-center justify-between gap-3 group"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700/90">{{ actionTypeLabel(a.type) }}</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 group-hover:text-[#0f3d7a] transition-colors truncate">{{ a.label }}</p>
                                <p v-if="a.count > 1" class="mt-0.5 text-xs text-amber-700 font-medium">{{ a.count }} items pending</p>
                            </div>
                            <div v-if="a.url" class="shrink-0">
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-[#0f3d7a] bg-slate-100 group-hover:bg-[#0f3d7a] group-hover:text-white px-3 py-1.5 rounded-lg transition-all shadow-xs">
                                    Open →
                                </span>
                            </div>
                        </component>
                    </div>
                </div>

                <div v-if="dashboardExtras.upcoming?.length" class="card">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="section-title text-base flex items-center gap-2">
                            <span>⏳</span> Upcoming deadlines
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">{{ dashboardExtras.upcoming.length }} dates</span>
                    </div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        <li v-for="(u, i) in dashboardExtras.upcoming" :key="i" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 hover:bg-slate-50/60 px-2 rounded-lg transition-colors">
                            <Link v-if="u.url" :href="u.url" class="link-brand truncate font-semibold text-slate-800 hover:text-[#0f3d7a]">{{ u.title }}</Link>
                            <span v-else class="truncate font-semibold text-slate-800">{{ u.title }}</span>
                            <span class="shrink-0 text-xs font-mono font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80">{{ u.date }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="dashboardExtras.recentResults?.length" class="card">
                    <h3 class="section-title mb-3 text-base">Recent results</h3>
                    <ul class="divide-y text-sm">
                        <li v-for="(r, i) in dashboardExtras.recentResults" :key="i" class="py-2 capitalize">
                            <span class="font-medium text-slate-800">{{ r.title }}</span>
                            <span class="text-xs text-slate-500"> · {{ r.type?.replace(/_/g, ' ') }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Recent activity -->
            <div v-if="recentActivity?.length" class="card">
                <h3 class="section-title mb-4 text-base">Recent activity</h3>
                <ul class="activity-timeline">
                    <li v-for="entry in recentActivity" :key="entry.id" class="activity-item">
                        <span class="activity-dot" :class="entry.action === 'created' && 'activity-dot--success'" />
                        <div class="min-w-0 flex-1 pb-1">
                            <p class="text-sm font-medium text-slate-800">{{ entry.description }}</p>
                            <p class="mt-0.5 text-xs capitalize text-slate-500">
                                {{ entry.log_name?.replace(/_/g, ' ') || entry.action }}
                                <span v-if="entry.created_at"> · {{ formatActivityDate(entry.created_at) }}</span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';
import QuickActionCard from '@/Components/ui/QuickActionCard.vue';
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
    cpd: { type: Object, default: null },
    boardResultsWidget: { type: Object, default: null },
    setup:  { type: Object, required: true },
    membershipComplete: { type: Object, default: null },
    leadershipContacts: { type: Object, default: null },
    recentActivity: { type: Array, default: () => [] },
    registrationWindow: { type: Object, default: null },
    showSetupWizard: { type: Boolean, default: true },
    documentAlerts: { type: Object, default: () => ({}) },
});

const dismissForm = useForm({});
const base = computed(() => `/school-admin/${props.school.id}`);

const stepTwoDone = computed(() =>
    props.setup.requiresStudents ? props.setup.studentCount > 0 : true
);

const setupComplete = computed(() =>
    props.setup.hasSchoolCode && stepTwoDone.value && props.setup.hasRegistration
);

const setupStepsDone = computed(() => {
    let n = 0;
    if (props.setup.hasSchoolCode) n++;
    if (stepTwoDone.value) n++;
    if (props.setup.hasRegistration) n++;
    return n;
});

const setupProgress = computed(() => Math.round((setupStepsDone.value / 3) * 100));

function dismissWizard() {
    dismissForm.post(`${base.value}/setup/dismiss-wizard`);
}

const registrationClosingDays = computed(() => windowClosingDays(props.registrationWindow));

const registrationClosingSoon = computed(() =>
    windowClosingSoon(props.registrationWindow) && !props.membershipComplete
);

const openProgramSummaries = computed(() =>
    (props.programSummaries ?? []).filter(p => (p.open_events ?? 0) > 0)
);

const alertItems = computed(() => {
    const items = [];
    const docs = props.documentAlerts ?? {};
    const docTotal = (docs.expired ?? 0) + (docs.expiring_soon ?? 0) + (docs.rejected ?? 0) + (docs.pending ?? 0);
    if (docTotal > 0) {
        const parts = [];
        if (docs.pending) parts.push(`${docs.pending} pending upload`);
        if (docs.rejected) parts.push(`${docs.rejected} rejected`);
        if (docs.expiring_soon) parts.push(`${docs.expiring_soon} expiring soon`);
        if (docs.expired) parts.push(`${docs.expired} expired`);
        items.push({
            tone: 'warning',
            title: 'Compliance documents need attention',
            body: parts.join(' · '),
            href: `${base.value}/documents`,
            linkLabel: 'Manage documents',
        });
    }
    if (props.leadershipContacts && !props.leadershipContacts.complete) {
        items.push({
            tone: 'warning',
            title: 'Leadership contacts pending',
            body: `${props.leadershipContacts.summary}.`,
            href: `${base.value}/registration/profile`,
            linkLabel: 'Complete registration profile',
        });
    }
    if (registrationClosingSoon.value) {
        items.push({
            tone: 'warning',
            title: 'Annual registration closes soon',
            body: `Closes on ${formatWindowDate(windowDisplayEnd(props.registrationWindow))} (${registrationClosingDays.value} day${registrationClosingDays.value === 1 ? '' : 's'} left).`,
            href: `${base.value}/registration`,
            linkLabel: 'Continue registration',
        });
    }
    if (props.membershipComplete) {
        items.push({
            tone: 'success',
            title: `${props.membershipComplete.academicYear} membership complete`,
            body: `Membership No: ${props.membershipComplete.regNo}`,
        });
    }
    return items;
});

const quickActions = computed(() => {
    const actions = [
        { label: 'Students', description: 'Manage records', icon: '👨‍🎓', href: `${base.value}/students` },
        { label: 'Registration', description: 'Annual membership', icon: '📋', href: `${base.value}/registration` },
        { label: 'Teachers', description: 'Staff directory', icon: '👩‍🏫', href: `${base.value}/teachers` },
        { label: 'Profile', description: 'Registration details', icon: '⚙️', href: `${base.value}/registration/profile` },
    ];
    if (props.setup.hasSchoolCode) {
        actions.splice(1, 0, {
            label: 'Bulk upload',
            description: 'CSV, grid, or photos',
            icon: '📤',
            href: `${base.value}/students?bulk=1`,
        });
    }
    return actions;
});

const linkedStats = computed(() => [
    {
        label: 'Active students',
        value: props.stats?.[0]?.value ?? 0,
        href: `${base.value}/students`,
        icon: '👨‍🎓',
        tone: 'navy',
        hint: 'View all students',
    },
    {
        label: 'Teachers',
        value: props.stats?.[1]?.value ?? 0,
        href: `${base.value}/teachers`,
        icon: '👩‍🏫',
        tone: 'indigo',
    },
    {
        label: 'Annual registration',
        value: props.membershipComplete ? 'Complete ✓' : (props.setup.hasRegistration ? 'In progress' : 'Start'),
        href: `${base.value}/registration`,
        icon: '📋',
        tone: props.membershipComplete ? 'green' : 'gold',
        hint: props.setup.academicYear ?? null,
    },
]);

function programIcon(slug) {
    const map = {
        kalotsav: '🎭',
        sports: '⚽',
        science: '🔬',
        english: '📚',
        mcq: '📝',
    };
    const key = String(slug ?? '').split('/').pop()?.replace(/-fest$/, '') ?? '';
    return map[key] ?? '🏆';
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function actionTypeLabel(type) {
    const labels = {
        fest_fee: 'Event fee',
        mcq_fee: 'Talent Search fee',
        mcq_fee_review: 'Talent Search payment',
        membership: 'Membership',
        mcq_register: 'Talent Search registration',
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
