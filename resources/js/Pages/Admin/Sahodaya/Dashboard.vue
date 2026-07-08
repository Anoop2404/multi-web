<template>
    <SahodayaAdminLayout title="Dashboard" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">

            <!-- Hero -->
            <div class="dash-hero">
                <p class="dash-hero-eyebrow">Sahodaya admin</p>
                <h1 class="dash-hero-title">{{ sahodaya.name }}</h1>
                <p class="dash-hero-desc">
                    Manage member schools, membership payments, fest programs, and circulars from one place.
                </p>
                <div class="dash-hero-badges">
                    <span v-if="stats.approved_schools != null" class="dash-badge dash-badge--gold">
                        {{ stats.approved_schools }} member schools
                    </span>
                    <span v-if="(stats.pending_schools ?? pendingSchoolsCount) > 0" class="dash-badge">
                        {{ stats.pending_schools ?? pendingSchoolsCount }} pending applications
                    </span>
                    <span v-if="stats.active_fest_events" class="dash-badge dash-badge--success">
                        {{ stats.active_fest_events }} active fest{{ stats.active_fest_events === 1 ? '' : 's' }}
                    </span>
                </div>
            </div>

            <!-- Setup reminder (optional — full wizard lives at /setup) -->
            <div v-if="showSetupBanner" class="card border-[#0f3d7a]/15 bg-gradient-to-br from-slate-50 to-white">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase tracking-widest text-[#fbbf24]">Setup in progress</p>
                        <h2 class="section-title mt-1 text-base">Finish configuring your Sahodaya</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ setupCompletedCount }}/{{ setupTotalSteps }} steps complete.
                            Schools need registration windows and fees before they can join.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link :href="`${adminBase}/setup`" class="btn-primary text-sm">Open setup wizard</Link>
                        <button type="button" class="btn-secondary text-sm" @click="dismissSetupBanner">
                            Dismiss
                        </button>
                    </div>
                </div>

                <div class="setup-progress mt-4" role="progressbar"
                     :aria-valuenow="setupProgress" aria-valuemin="0" aria-valuemax="100">
                    <div class="setup-progress-bar" :style="{ width: `${setupProgress}%` }" />
                </div>

                <ul v-if="pendingSetupSteps.length" class="mt-4 grid gap-2 sm:grid-cols-2">
                    <li v-for="step in pendingSetupSteps" :key="step.key"
                        class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2 text-sm">
                        <span class="font-medium text-slate-800">{{ step.label }}</span>
                        <Link :href="step.href" class="link-brand shrink-0 text-xs">Configure →</Link>
                    </li>
                </ul>
            </div>

            <!-- Action queue -->
            <div v-if="actionQueueItems.length" class="card border-amber-100 bg-gradient-to-br from-amber-50/40 to-white">
                <h3 class="section-title mb-4 text-base">Action queue</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <ActionBanner v-for="item in actionQueueItems" :key="item.key"
                                  :href="item.href" :count="item.count" :label="item.label"
                                  :color="item.color" :icon="item.icon" />
                </div>
            </div>

            <!-- Get started -->
            <div v-if="canSee('membership') && !(stats.approved_schools ?? 0)" class="card card--accent">
                <h3 class="section-title mb-4 text-base">Get started</h3>
                <div class="setup-step-grid">
                    <div class="setup-step-card setup-step-card--active">
                        <span class="step-badge step-badge--active mb-3 inline-flex">1</span>
                        <p class="font-semibold text-slate-900">Academic year</p>
                        <p class="mt-1 text-xs text-slate-500">Set the current year for registrations.</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/academic-years`" class="link-brand mt-3 inline-block text-xs">
                            Configure →
                        </Link>
                    </div>
                    <div class="setup-step-card">
                        <span class="step-badge step-badge--pending mb-3 inline-flex">2</span>
                        <p class="font-semibold text-slate-900">Member schools</p>
                        <p class="mt-1 text-xs text-slate-500">Invite and approve school applications.</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="link-brand mt-3 inline-block text-xs">
                            View schools →
                        </Link>
                    </div>
                    <div class="setup-step-card">
                        <span class="step-badge step-badge--pending mb-3 inline-flex">3</span>
                        <p class="font-semibold text-slate-900">First fest event</p>
                        <p class="mt-1 text-xs text-slate-500">Publish a Kalotsav or program event.</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`" class="link-brand mt-3 inline-block text-xs">
                            Kalotsav hub →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Key stats -->
            <div v-if="primaryStats.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <DashboardStatCard
                    v-for="stat in primaryStats"
                    :key="stat.label"
                    :label="stat.label"
                    :value="stat.value"
                    :icon="stat.icon"
                    :hint="stat.hint"
                    :href="stat.href"
                    :tone="stat.tone"
                />
            </div>

            <!-- Quick actions -->
            <div v-if="quickActions.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <QuickActionCard
                    v-for="action in quickActions"
                    :key="action.label"
                    :href="action.href"
                    :label="action.label"
                    :description="action.description"
                    :icon="action.icon"
                />
            </div>

            <!-- Program status -->
            <div v-if="dashboardExtras?.programStatus?.length" class="card">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="section-title text-base">Program status</h3>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events`" class="link-brand text-xs">All events →</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link v-for="p in dashboardExtras.programStatus" :key="p.key" :href="p.hub_url" class="program-card group">
                        <div class="flex items-center gap-3">
                            <span class="program-card-icon">{{ programIcon(p.key) }}</span>
                            <p class="font-semibold text-slate-900 group-hover:text-[#0f3d7a]">{{ p.label }}</p>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ p.open_events }} open · {{ p.registrations }} registrations
                            <span v-if="p.results_pending" class="font-semibold text-amber-700">
                                · {{ p.results_pending }} results pending
                            </span>
                        </p>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Active fest events -->
                <div v-if="canSee('fest')" class="card">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="section-title text-base">Active fest events</h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events`" class="link-brand text-xs">All events →</Link>
                    </div>
                    <div v-if="activeEvents.length" class="divide-y divide-slate-100">
                        <div v-for="ev in activeEvents" :key="ev.id"
                             class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ ev.title }}</p>
                                <p class="mt-0.5 text-xs capitalize text-slate-500">
                                    {{ ev.event_type?.replace(/_/g, ' ') }} · {{ ev.status?.replace(/_/g, ' ') }}
                                    <span v-if="ev.registrations_count != null"> · {{ ev.registrations_count }} reg.</span>
                                </p>
                            </div>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${ev.id}`" class="link-brand shrink-0 text-xs">
                                Manage →
                            </Link>
                        </div>
                    </div>
                    <EmptyState v-else title="No active fest events" description="Open a program hub to create or publish an event." icon="🏆">
                        <template #action>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`" class="btn-secondary text-xs">Kalotsav hub</Link>
                        </template>
                    </EmptyState>
                </div>

                <!-- Recent circulars -->
                <div v-if="websiteEnabled && canSee('website')" class="card">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="section-title text-base">Recent circulars</h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`" class="link-brand text-xs">View all →</Link>
                    </div>
                    <div v-if="recentCirculars.length" class="divide-y divide-slate-100">
                        <div v-for="c in recentCirculars" :key="c.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <span class="mt-0.5 text-lg">📄</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ c.title }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else title="No circulars yet" description="Upload notices for member schools." icon="📄" />
                </div>
            </div>

            <!-- Recent activity -->
            <div v-if="recentActivity?.length" class="card">
                <h3 class="section-title mb-4 text-base">Recent activity</h3>
                <ul class="activity-timeline">
                    <li v-for="entry in recentActivity" :key="entry.id" class="activity-item">
                        <span class="activity-dot" />
                        <div class="min-w-0 flex-1 pb-1">
                            <p class="text-sm font-medium text-slate-800">{{ entry.description || entry.action }}</p>
                            <p class="mt-0.5 text-xs capitalize text-slate-500">
                                {{ entry.category?.replace(/_/g, ' ') || entry.action }}
                                <span v-if="entry.created_at"> · {{ formatActivityDate(entry.created_at) }}</span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import DashboardStatCard from '@/Components/ui/DashboardStatCard.vue';
import QuickActionCard from '@/Components/ui/QuickActionCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, defineComponent, h } from 'vue';

const page = usePage();
const websiteEnabled = computed(() => page.props.features?.website_enabled ?? false);
const isStaffUser = computed(() => page.props.isStaff);

const STAFF_SECTIONS = {
    website: ['website.view', 'website.manage', 'website.news'],
    membership: ['membership.view', 'membership.manage'],
    fest: ['fest.view', 'fest.manage', 'fest.marks', 'fest.registrations', 'fest.results', 'fest.settings', 'fest.finance', 'fest.certificates', 'fest.catering', 'fest.schedule'],
    mcq: ['mcq.view', 'mcq.manage', 'mcq.attendance', 'mcq.marks'],
    training: ['training.view', 'training.manage', 'fest.view', 'fest.manage'],
    ledger: ['finance.view', 'membership.view', 'membership.manage'],
};

function canSee(section) {
    if (!isStaffUser.value) return true;
    const perms = page.props.staffPermissions ?? [];
    const required = STAFF_SECTIONS[section];
    if (!required) return true;
    return required.some((p) => perms.includes(p));
}

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    stats:                   { type: Object, default: () => ({}) },
    actionQueue:             { type: Object, default: () => ({}) },
    actionQueueLinks:        { type: Object, default: () => ({}) },
    recentCirculars:         { type: Array,  default: () => [] },
    activeEvents:            { type: Array, default: () => [] },
    festOps:                 { type: Object, default: () => ({ programs: [] }) },
    dashboardExtras:         { type: Object, default: () => ({}) },
    recentActivity:          { type: Array, default: () => [] },
    setupChecklist:          { type: Array, default: () => [] },
    setupCompletedCount:     { type: Number, default: 0 },
    setupTotalSteps:         { type: Number, default: 0 },
    setupAllStepsComplete:   { type: Boolean, default: false },
    showSetupBanner:         { type: Boolean, default: false },
});

const adminBase = computed(() => `/sahodaya-admin/${props.sahodaya?.id}`);

const dismissSetupForm = useForm({});

const setupProgress = computed(() =>
    props.setupTotalSteps
        ? Math.round((props.setupCompletedCount / props.setupTotalSteps) * 100)
        : 0
);

const pendingSetupSteps = computed(() =>
    (props.setupChecklist ?? []).filter((step) => !step.done).slice(0, 4)
);

function dismissSetupBanner() {
    dismissSetupForm.post(`${adminBase.value}/setup/dismiss`);
}

const actionQueueItems = computed(() => {
    const q = props.actionQueue ?? {};
    const base = adminBase.value;
    const map = {
        membership_data_pending: { href: `${base}/membership/reports?tab=submissions`, label: 'membership data reviews pending', color: 'amber', icon: '📋' },
        pending_school_applications: { href: `${base}/schools/applications`, label: 'school applications awaiting review', color: 'amber', icon: '🏫' },
        membership_payments: { href: `${base}/membership/payments`, label: 'membership payments to verify', color: 'green', icon: '💳' },
        fest_fee_proofs: { href: `${base}/fest/payments`, label: 'fest fee proofs awaiting approval', color: 'blue', icon: '🏆' },
        mcq_fee_proofs: { href: `${base}/mcq/payments`, label: 'Talent Search batch fees awaiting approval', color: 'indigo', icon: '📝' },
        fest_appeals: { href: `${base}/events`, label: 'open fest appeals', color: 'amber', icon: '⚖️' },
        fest_registrations_review: { href: `${base}/events`, label: 'fest registrations to review', color: 'navy', icon: '📥' },
    };
    return Object.entries(q).map(([key, count]) => {
        const meta = map[key] ?? {};
        const href = props.actionQueueLinks?.[key] ?? meta.href;
        return { key, count, ...meta, href };
    }).filter((i) => i.label);
});

const primaryStats = computed(() => {
    const base = adminBase.value;
    const items = [];
    if (canSee('membership')) {
        items.push({
            label: 'Approved schools',
            value: props.stats.approved_schools ?? 0,
            href: `${base}/schools`,
            hint: (props.stats.pending_schools ?? 0) > 0 ? `${props.stats.pending_schools} pending →` : null,
            icon: '🏫',
            tone: 'navy',
        });
        items.push({
            label: 'Payments pending',
            value: `₹${Number(props.stats.payments_pending_verification_amount || props.stats.pending_amount || 0).toLocaleString('en-IN')}`,
            href: `${base}/membership/payments`,
            hint: `${props.stats.payments_pending_verification ?? props.pendingPaymentsCount ?? 0} to verify →`,
            icon: '💳',
            tone: 'amber',
        });
    }
    if (canSee('fest')) {
        items.push({
            label: 'Active fests',
            value: props.stats.active_fest_events ?? 0,
            href: `${base}/events`,
            hint: `${props.stats.fest_registrations ?? 0} registrations`,
            icon: '🏆',
            tone: 'green',
        });
    }
    return items;
});

const quickActions = computed(() => {
    const base = adminBase.value;
    const items = [];
    if (canSee('membership')) {
        items.push({ label: 'Schools', description: 'Member directory', icon: '🏫', href: `${base}/schools` });
        items.push({ label: 'Payments', description: 'Verify fees', icon: '💳', href: `${base}/membership/payments` });
    }
    if (canSee('fest')) {
        items.push({ label: 'Events', description: 'Fest management', icon: '🏆', href: `${base}/events` });
        items.push({ label: 'Kalotsav', description: 'Program hub', icon: '🎭', href: `${base}/kalotsav` });
        items.push({ label: 'Sports', description: 'Sports meet hub', icon: '⚽', href: `${base}/sports` });
        items.push({ label: 'English Fest', description: 'Program hub', icon: '📚', href: `${base}/english-fest` });
        items.push({ label: 'Science Fest', description: 'Program hub', icon: '🔬', href: `${base}/science-fest` });
    }
    if (canSee('website') && websiteEnabled.value) {
        items.push({ label: 'Circulars', description: 'Publish notices', icon: '📄', href: `${base}/circulars` });
    }
    if (canSee('mcq')) {
        items.push({ label: 'Talent Search', description: 'Exam management', icon: '📝', href: `${base}/mcq` });
    }
    return items.slice(0, 4);
});

function programIcon(key) {
    const map = {
        kalotsav: '🎭',
        sports: '⚽',
        science: '🔬',
        english: '📚',
        mcq: '📝',
    };
    const k = String(key ?? '').toLowerCase();
    for (const [name, icon] of Object.entries(map)) {
        if (k.includes(name)) return icon;
    }
    return '🏆';
}

function formatActivityDate(value) {
    if (!value) return '';
    return new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

const colorMap = {
    blue:   { bg: 'bg-blue-50',   text: 'text-blue-600',   num: 'text-blue-700' },
    navy:   { bg: 'bg-[#eff6ff]', text: 'text-[#1e5aa8]',  num: 'text-[#0f3d7a]' },
    indigo: { bg: 'bg-indigo-50', text: 'text-indigo-600', num: 'text-indigo-700' },
    amber:  { bg: 'bg-amber-50',  text: 'text-amber-600',  num: 'text-amber-700' },
    green:  { bg: 'bg-green-50',  text: 'text-green-600',  num: 'text-green-700' },
};

const ActionBanner = defineComponent({
    props: { href: String, count: Number, label: String, color: String, icon: String },
    setup(props) {
        const bannerStyles = {
            amber: 'border-amber-200 bg-white hover:border-amber-300 hover:shadow-sm',
            green: 'border-green-200 bg-white hover:border-green-300 hover:shadow-sm',
            blue:  'border-blue-200 bg-white hover:border-blue-300 hover:shadow-sm',
            navy:  'border-[#bfdbfe] bg-white hover:border-[#93c5fd] hover:shadow-sm',
            indigo: 'border-indigo-200 bg-white hover:border-indigo-300 hover:shadow-sm',
        };

        return () => {
            const c = colorMap[props.color] || colorMap.amber;
            const banner = bannerStyles[props.color] || bannerStyles.amber;

            return h(Link, {
                href: props.href,
                class: `flex items-center gap-3 rounded-xl border p-4 transition ${banner}`,
            }, {
                default: () => [
                    h('div', { class: 'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-xl' }, props.icon),
                    h('div', { class: 'min-w-0' }, [
                        h('p', { class: `text-xl font-extrabold ${c.num}` }, String(props.count)),
                        h('p', { class: 'text-xs text-slate-600' }, props.label),
                    ]),
                ],
            });
        };
    },
});
</script>
