<template>
    <SahodayaAdminLayout title="Dashboard" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">

            <!-- Action queue -->
            <div v-if="actionQueueItems.length" class="card">
                <h3 class="section-title text-base mb-4">Action queue</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <ActionBanner v-for="item in actionQueueItems" :key="item.key"
                                  :href="item.href" :count="item.count" :label="item.label"
                                  :color="item.color" :icon="item.icon" />
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="canSee('membership') && !(stats.approved_schools ?? 0)" class="card">
                <h3 class="section-title text-base mb-3">Get started</h3>
                <ol class="step-list text-sm text-slate-600 space-y-3">
                    <li class="flex gap-3"><span class="step-badge step-badge--active shrink-0">1</span><span>Set up an <Link :href="`/sahodaya-admin/${sahodaya.id}/academic-years`" class="link-brand">academic year</Link></span></li>
                    <li class="flex gap-3"><span class="step-badge step-badge--pending shrink-0">2</span><span>Invite and approve <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="link-brand">member schools</Link></span></li>
                    <li class="flex gap-3"><span class="step-badge step-badge--pending shrink-0">3</span><span>Publish your first <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`" class="link-brand">fest event</Link></span></li>
                </ol>
            </div>

            <!-- Key stats (linked) -->
            <div v-if="primaryStats.length" class="grid sm:grid-cols-3 gap-4">
                <Link v-for="stat in primaryStats" :key="stat.label" :href="stat.href"
                      class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-[#0f3d7a]/30 hover:shadow-sm transition block">
                    <p class="text-2xl font-extrabold text-[#0f3d7a]">{{ stat.value }}</p>
                    <p class="text-xs text-gray-500 font-medium mt-1">{{ stat.label }}</p>
                    <p v-if="stat.hint" class="text-[11px] text-indigo-600 mt-1">{{ stat.hint }}</p>
                </Link>
            </div>

            <!-- Program status -->
            <div v-if="dashboardExtras?.programStatus?.length" class="card">
                <h3 class="section-title text-base mb-4">Program status</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Link v-for="p in dashboardExtras.programStatus" :key="p.key" :href="p.hub_url"
                          class="rounded-xl border border-slate-200 p-4 hover:border-[#0f3d7a]/30 transition">
                        <p class="font-semibold text-slate-900">{{ p.label }}</p>
                        <p class="text-xs text-slate-500 mt-2">
                            {{ p.open_events }} open · {{ p.registrations }} registrations
                            <span v-if="p.results_pending" class="text-amber-700"> · {{ p.results_pending }} results pending</span>
                        </p>
                    </Link>
                </div>
            </div>

            <!-- Recent activity -->
            <div v-if="recentActivity?.length" class="card">
                <h3 class="section-title text-base mb-3">Recent activity</h3>
                <ul class="text-sm divide-y">
                    <li v-for="entry in recentActivity" :key="entry.id" class="py-2">
                        <p class="font-medium text-slate-800">{{ entry.description || entry.action }}</p>
                        <p class="text-xs text-slate-500 mt-0.5 capitalize">
                            {{ entry.category?.replace(/_/g, ' ') || entry.action }}
                            <span v-if="entry.created_at"> · {{ formatActivityDate(entry.created_at) }}</span>
                        </p>
                    </li>
                </ul>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <div v-if="canSee('fest')" class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="section-title text-base">Active fest events</h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events`" class="link-brand text-xs">All events →</Link>
                    </div>
                    <div v-if="activeEvents.length" class="divide-y divide-slate-100">
                        <div v-for="ev in activeEvents" :key="ev.id" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ ev.title }}</p>
                                <p class="text-xs text-slate-500 mt-0.5 capitalize">
                                    {{ ev.event_type?.replace(/_/g, ' ') }} · {{ ev.status?.replace(/_/g, ' ') }}
                                    <span v-if="ev.registrations_count != null"> · {{ ev.registrations_count }} reg.</span>
                                </p>
                            </div>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${ev.id}`" class="text-xs link-brand shrink-0">Manage →</Link>
                        </div>
                    </div>
                    <EmptyState v-else title="No active fest events" description="Open a program hub to create or publish an event." icon="🏆">
                        <template #action>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/kalotsav`" class="btn-secondary text-xs">Kalotsav hub</Link>
                        </template>
                    </EmptyState>
                </div>

                <div v-if="websiteEnabled && canSee('website')" class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="section-title text-base">Recent circulars</h3>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/circulars`" class="link-brand text-xs">View all →</Link>
                    </div>
                    <div v-if="recentCirculars.length" class="divide-y divide-slate-100">
                        <div v-for="c in recentCirculars" :key="c.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ c.title }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN', {day:'2-digit',month:'short'}) : '' }}</p>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else title="No circulars yet" description="Upload notices for member schools." icon="📄" />
                </div>
            </div>

        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
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
});

function programHubHref(p) {
    const base = `/sahodaya-admin/${props.sahodaya?.id}`;
    if (String(p.prefix).startsWith('programs/')) {
        return `${base}/${p.prefix}`;
    }
    return `${base}/${p.prefix}`;
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

const actionQueueItems = computed(() => {
    const q = props.actionQueue ?? {};
    const base = `/sahodaya-admin/${props.sahodaya?.id}`;
    const map = {
        membership_data_pending: { href: `${base}/membership/reports?tab=submissions`, label: 'membership data reviews pending', color: 'amber', icon: '📋' },
        pending_school_applications: { href: `${base}/schools/applications`, label: 'school applications awaiting review', color: 'amber', icon: '🏫' },
        membership_payments: { href: `${base}/membership/payments`, label: 'membership payments to verify', color: 'green', icon: '💳' },
        fest_fee_proofs: { href: `${base}/fest/payments`, label: 'fest fee proofs awaiting approval', color: 'blue', icon: '🏆' },
        mcq_fee_proofs: { href: `${base}/mcq/payments`, label: 'MCQ batch fees awaiting approval', color: 'indigo', icon: '📝' },
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
    const base = `/sahodaya-admin/${props.sahodaya?.id}`;
    const items = [];
    if (canSee('membership')) {
        items.push({
            label: 'Approved schools',
            value: props.stats.approved_schools ?? 0,
            href: `${base}/schools`,
            hint: (props.stats.pending_schools ?? 0) > 0 ? `${props.stats.pending_schools} pending →` : null,
        });
        items.push({
            label: 'Payments pending',
            value: `₹${Number(props.stats.payments_pending_verification_amount || props.stats.pending_amount || 0).toLocaleString('en-IN')}`,
            href: `${base}/membership/payments`,
            hint: `${props.stats.payments_pending_verification ?? props.pendingPaymentsCount ?? 0} to verify →`,
        });
    }
    if (canSee('fest')) {
        items.push({
            label: 'Active fests',
            value: props.stats.active_fest_events ?? 0,
            href: `${base}/events`,
            hint: `${props.stats.fest_registrations ?? 0} registrations`,
        });
    }
    return items;
});

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

const StatCard = defineComponent({
    props: { value: [Number, String], label: String, color: String, icon: String, hint: String },
    setup(props) {
        return () => {
            const c = colorMap[props.color] || colorMap.navy;
            return h('div', { class: `${c.bg} rounded-2xl p-5 flex items-center gap-4` }, [
                h('div', { class: `w-11 h-11 rounded-xl bg-white/60 flex items-center justify-center text-xl shrink-0 shadow-sm` }, props.icon),
                h('div', {}, [
                    h('p', { class: `text-2xl font-extrabold ${c.num}` }, props.value ?? '—'),
                    h('p', { class: `text-xs text-gray-500 font-medium mt-0.5` }, props.label),
                    props.hint ? h('p', { class: 'text-[10px] text-gray-400 mt-0.5' }, props.hint) : null,
                ]),
            ]);
        };
    },
});

const ActionBanner = defineComponent({
    props: { href: String, count: Number, label: String, color: String, icon: String },
    setup(props) {
        const bannerStyles = {
            amber: 'border-amber-200 bg-amber-50 hover:bg-amber-100',
            green: 'border-green-200 bg-green-50 hover:bg-green-100',
            blue:  'border-blue-200 bg-blue-50 hover:bg-blue-100',
        };

        return () => {
            const c = colorMap[props.color] || colorMap.amber;
            const banner = bannerStyles[props.color] || bannerStyles.amber;

            return h(Link, {
                href: props.href,
                class: `flex items-center gap-3 p-4 rounded-2xl border-2 transition ${banner}`,
            }, {
                default: () => [
                    h('div', { class: 'w-10 h-10 rounded-xl bg-white/70 flex items-center justify-center text-xl shrink-0' }, props.icon),
                    h('div', {}, [
                        h('p', { class: `text-lg font-extrabold ${c.num}` }, String(props.count)),
                        h('p', { class: 'text-xs text-gray-600' }, props.label),
                    ]),
                ],
            });
        };
    },
});

const QuickAction = defineComponent({
    props: { href: String, icon: String, label: String, desc: String },
    setup(props) {
        return () => h(Link, {
            href: props.href,
            class: 'flex items-start gap-3 p-4 rounded-2xl border border-slate-200/90 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition duration-150 hover:-translate-y-0.5 hover:border-[#0f3d7a]/30 hover:shadow-[0_4px_16px_rgba(15,61,122,0.1)] group',
        }, {
            default: () => [
                h('div', { class: 'w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-[color:var(--brand-gold-soft)] flex items-center justify-center text-xl shrink-0 transition' }, props.icon),
                h('div', {}, [
                    h('p', { class: 'text-sm font-semibold text-gray-800' }, props.label),
                    h('p', { class: 'text-xs text-gray-400 mt-0.5' }, props.desc),
                ]),
            ],
        });
    },
});
</script>
